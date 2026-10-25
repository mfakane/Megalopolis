<?php
namespace Megalopolis;

use \PDO;

/**
 * MySQL の utf8 (3 バイト) のテーブルを utf8mb4 に変換する。
 *
 * テーブルを一度に変換すると大きなデータベースでは時間がかかるため、utf8mb4 の新しい
 * テーブルを作って少しずつコピーし、最後にまとめて入れ替える。変換中は書き込みを止め、
 * 進み具合を meta に記録して、中断しても続きから再開できるようにする。
 */
class CharsetConversion
{
	const string NEW_SUFFIX = "__mb4";
	const string OLD_SUFFIX = "__utf8";
	const string TABLE_META_NAME = "charsetConversionTable";
	const string PHASE_META_NAME = "charsetConversionPhase";
	const string LAST_ID_META_NAME = "charsetConversionLastId";
	const string PHASE_COPY = "copy";
	const string PHASE_DECODE = "decode";
	const string LOCK_NAME = "megalopolis_charset_conversion";
	const int COPY_IDS = 500;
	const int DECODE_IDS = 200;

	private PDO $db;
	private MySQLDataStore $store;

	function __construct(PDO $db, MySQLDataStore $store)
	{
		$this->db = $db;
		$this->store = $store;
	}

	/**
	 * コピーせず、その場で変換するか作り直すテーブル
	 * @return string[]
	 */
	private static function skippedTables(): array
	{
		return array(
			App::META_TABLE,
			App::SESSION_STORE_TABLE,
			App::AUTHOR_TABLE,
			App::TAG_TABLE,
			SQLiteSearchIndex::INDEX_TABLE,
			ClassicSearchIndex::INDEX_TABLE,
			SearchIndex::PHRASE_FORMAT_TABLE
		);
	}

	/**
	 * 変換の対象になるテーブル (名前順)
	 * @return array<string, array{rows: int, engine: string}>
	 */
	function getTables(): array
	{
		$skipped = array_map("strtolower", self::skippedTables());
		$st = $this->db->query("
			select table_name, table_rows, engine from information_schema.tables
			where table_schema = database() and table_type = 'BASE TABLE' and table_collation not like 'utf8mb4%'
			order by table_name");
		$rt = array();

		foreach ($st ? $st->fetchAll(PDO::FETCH_NUM) : array() as $i) {
			$name = (string)$i[0];

			if (in_array(strtolower($name), $skipped) || self::isWorkTable($name))
				continue;

			$rt[$name] = array("rows" => (int)$i[1], "engine" => (string)$i[2]);
		}

		// 進み具合の比較 (strcmp) と同じ順にする
		ksort($rt, SORT_STRING);

		return $rt;
	}

	private static function isWorkTable(string $name): bool
	{
		return str_ends_with($name, self::NEW_SUFFIX) || str_ends_with($name, self::OLD_SUFFIX);
	}

	/**
	 * @return string[]
	 */
	private function getTablesWithSuffix(string $suffix): array
	{
		$st = $this->db->query("
			select table_name from information_schema.tables
			where table_schema = database() and table_type = 'BASE TABLE'
			order by table_name");

		return array_values(array_filter($st ? $st->fetchAll(PDO::FETCH_COLUMN, 0) : array(), fn($x) => str_ends_with((string)$x, $suffix)));
	}

	function isConverting(): bool
	{
		return Meta::get($this->db, self::TABLE_META_NAME) !== null;
	}

	/**
	 * @return array<string, mixed>
	 */
	function getStatus(): array
	{
		$tables = $this->getTables();
		$current = Meta::get($this->db, self::TABLE_META_NAME);

		return array(
			"fullUnicode" => $this->store->isFullUnicode(),
			"converting" => $current !== null,
			"maintenance" => $this->store->isMaintenance(),
			"tables" => array_map(fn($k, $v) => array("name" => $k, "rows" => $v["rows"], "done" => $current !== null && ($current === "" || strcmp($k, $current) < 0)), array_keys($tables), array_values($tables)),
			"current" => $current,
			"phase" => Meta::get($this->db, self::PHASE_META_NAME),
			"oldTables" => $this->getTablesWithSuffix(self::OLD_SUFFIX)
		);
	}

	/**
	 * 同時に複数の変換処理が動かないようにする
	 */
	function lock(): void
	{
		$st = $this->db->prepare("select get_lock(?, 0)");
		$st->execute(array(self::LOCK_NAME));

		if ((int)$st->fetchColumn() !== 1)
			throw new ApplicationException("他の変換処理が実行中です", 409);
	}

	function unlock(): void
	{
		$this->db->prepare("select release_lock(?)")->execute(array(self::LOCK_NAME));
	}

	/**
	 * 変換先のテーブルを作り、書き込みを止める。
	 * 作れない場合 (インデックスが長すぎる、権限が無いなど) は何も変更しない
	 */
	function start(): void
	{
		if ($this->store->isFullUnicode())
			throw new ApplicationException("既に utf8mb4 に変換されています", 400);

		if ($this->isConverting())
			return;

		foreach ($this->getTablesWithSuffix(self::NEW_SUFFIX) as $i)
			$this->db->exec("drop table {$i}");

		$tables = $this->getTables();
		$created = array();

		try {
			foreach ($tables as $name => $info) {
				$new = $name . self::NEW_SUFFIX;
				$this->db->exec("create table {$new} like {$name}");
				$created[] = $new;
				$this->db->exec(sprintf(
					"alter table %s %s convert to character set utf8mb4 collate %s",
					$new,
					strcasecmp($info["engine"], "InnoDB") == 0 ? "row_format=dynamic," : "",
					MySQLDataStore::COLLATION
				));
			}

			// 入れ替えた後で作者・タグの集計用のトリガーを作り直せるか確かめる
			if (isset($tables[App::THREAD_ENTRY_TABLE])) {
				$table = App::THREAD_ENTRY_TABLE . self::NEW_SUFFIX;
				$this->db->exec("create trigger {$table}CheckTrigger after insert on {$table} for each row set @megalopolis = 1");
				$this->db->exec("drop trigger {$table}CheckTrigger");
			}
		} catch (\PDOException $ex) {
			foreach ($created as $i)
				$this->db->exec("drop table if exists {$i}");

			throw new ApplicationException("変換を開始できませんでした: " . $ex->getMessage(), 500);
		}

		$this->store->setMaintenance($this->db, true);
		$this->setProgress(array_key_first($tables) ?? "", self::PHASE_COPY, null);
	}

	private function setProgress(string $table, ?string $phase, ?string $lastID): void
	{
		Meta::set($this->db, self::TABLE_META_NAME, $table);
		Meta::set($this->db, self::PHASE_META_NAME, $phase);
		Meta::set($this->db, self::LAST_ID_META_NAME, $lastID);
	}

	/**
	 * 決められた時間の間、コピーと数値文字参照の復元を進める
	 * @return bool すべてのテーブルを処理し終えたか
	 */
	function step(float $seconds): bool
	{
		if (!$this->isConverting())
			throw new ApplicationException("変換は開始されていません", 400);

		$until = microtime(true) + $seconds;

		// 元に戻した 4 バイトの文字を送れるよう、この処理の間は接続を utf8mb4 にする
		$this->db->exec("set names utf8mb4 collate " . MySQLDataStore::COLLATION);

		do {
			$table = (string)Meta::get($this->db, self::TABLE_META_NAME);

			if ($table === "")
				return true;

			$phase = Meta::get($this->db, self::PHASE_META_NAME);
			$lastID = Meta::get($this->db, self::LAST_ID_META_NAME);
			$hasID = $this->hasColumn($table, "id");

			if ($phase == self::PHASE_COPY) {
				if ($hasID && ($nextID = $this->getNextID($table, $lastID, self::COPY_IDS)) !== null)
					$this->inTransaction(function () use ($table, $lastID, $nextID) {
						$this->copyRows($table, $lastID, $nextID);
						Meta::set($this->db, self::LAST_ID_META_NAME, $nextID);
					});
				else
					$this->inTransaction(function () use ($table, $hasID) {
						if (!$hasID)
							$this->copyRows($table, null, null);

						$this->setProgress($table, self::PHASE_DECODE, null);
					});
			} else if ($hasID && ($nextID = $this->getNextID($table . self::NEW_SUFFIX, $lastID, self::DECODE_IDS, $this->getTextCondition($table))) !== null) {
				$this->inTransaction(function () use ($table, $lastID, $nextID) {
					$this->decodeRows($table, $lastID, $nextID);
					Meta::set($this->db, self::LAST_ID_META_NAME, $nextID);
				});
			} else {
				$this->inTransaction(function () use ($table, $hasID) {
					if (!$hasID)
						$this->decodeRows($table, null, null);

					$this->setProgress($this->getNextTable($table) ?? "", self::PHASE_COPY, null);
				});
			}
		} while (microtime(true) < $until);

		return Meta::get($this->db, self::TABLE_META_NAME) === "";
	}

	private function inTransaction(callable $callback): void
	{
		$this->db->beginTransaction();

		try {
			$callback();
			$this->db->commit();
		} catch (\Throwable $ex) {
			$this->db->rollBack();

			throw $ex;
		}
	}

	private function getNextTable(string $table): ?string
	{
		foreach (array_keys($this->getTables()) as $i)
			if (strcmp($i, $table) > 0)
				return $i;

		return null;
	}

	private function hasColumn(string $table, string $column): bool
	{
		$st = $this->db->prepare("select count(*) from information_schema.columns where table_schema = database() and table_name = ? and column_name = ?");
		$st->execute(array($table, $column));

		return (int)$st->fetchColumn() > 0;
	}

	/**
	 * 次に処理する範囲の最後の id。id ごとに複数の行がある (threadTag など) ため、id の範囲で区切る
	 */
	private function getNextID(string $table, ?string $lastID, int $count, string $condition = ""): ?string
	{
		$st = $this->db->prepare(sprintf(
			"select max(id) from (select distinct id from %s where %s %s order by id limit %d) as ids",
			$table,
			$lastID === null ? "1 = 1" : "id > ?",
			$condition === "" ? "" : "and ({$condition})",
			$count
		));
		$st->execute($lastID === null ? array() : array($lastID));
		$rt = $st->fetchColumn();

		return $rt === null || $rt === false ? null : (string)$rt;
	}

	private function copyRows(string $table, ?string $lastID, ?string $nextID): void
	{
		$sql = sprintf("insert into %s select * from %s", $table . self::NEW_SUFFIX, $table);

		if ($nextID === null)
			$this->db->exec($sql);
		else
			$this->db->prepare($sql . ($lastID === null ? " where id <= ?" : " where id > ? and id <= ?"))
				->execute($lastID === null ? array($nextID) : array($lastID, $nextID));
	}

	/**
	 * @return string[]
	 */
	private function getTextColumns(string $table): array
	{
		$st = $this->db->prepare("
			select column_name from information_schema.columns
			where table_schema = database() and table_name = ?
			and data_type in ('char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext')");
		$st->execute(array($table));

		return $st->fetchAll(PDO::FETCH_COLUMN, 0);
	}

	/**
	 * @return string[]
	 */
	private function getPrimaryKey(string $table): array
	{
		$st = $this->db->prepare("
			select column_name from information_schema.key_column_usage
			where table_schema = database() and table_name = ? and constraint_name = 'PRIMARY'
			order by ordinal_position");
		$st->execute(array($table));

		return $st->fetchAll(PDO::FETCH_COLUMN, 0);
	}

	private function getTextCondition(string $table): string
	{
		$columns = $this->getTextColumns($table);

		return $columns ? implode(" or ", array_map(fn($x) => "{$x} like '%&#%'", $columns)) : "1 = 0";
	}

	/**
	 * utf8 のテーブルに保存するために数値文字参照にしていた 4 バイトの文字を元に戻す
	 */
	static function decodeReferences(string $s): string
	{
		return (string)preg_replace_callback(
			'/&#(\d+);/',
			fn($m) => (int)$m[1] >= 0x10000 && (int)$m[1] <= 0x10FFFF ? (string)mb_chr((int)$m[1], "UTF-8") : $m[0],
			$s
		);
	}

	private function decodeRows(string $table, ?string $lastID, ?string $nextID): void
	{
		$columns = $this->getTextColumns($table);
		$keys = $this->getPrimaryKey($table);

		if (!$columns || !$keys)
			return;

		$new = $table . self::NEW_SUFFIX;
		$range = $nextID === null ? "1 = 1" : ($lastID === null ? "id <= ?" : "id > ? and id <= ?");
		$params = $nextID === null ? array() : ($lastID === null ? array($nextID) : array($lastID, $nextID));
		$st = $this->db->prepare(sprintf(
			"select %s from %s where %s and (%s)",
			implode(", ", array_unique(array_merge($keys, $columns))),
			$new,
			$range,
			$this->getTextCondition($table)
		));
		$st->execute($params);

		foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$changes = array();

			foreach ($columns as $i)
				if (is_string($row[$i]) && ($decoded = self::decodeReferences($row[$i])) !== $row[$i])
					$changes[$i] = $decoded;

			if (!$changes)
				continue;

			try {
				$this->db->prepare(sprintf(
					"update %s set %s where %s",
					$new,
					implode(", ", array_map(fn($x) => "{$x} = ?", array_keys($changes))),
					implode(" and ", array_map(fn($x) => "{$x} = ?", $keys))
				))->execute(array_merge(array_values($changes), array_map(fn($x) => $row[$x], $keys)));
			} catch (\PDOException $ex) {
				// utf8mb4_general_ci では 4 バイトの文字どうしが等しいとみなされるため、
				// 同じ作品に絵文字だけが違うタグがあると重複する。その行は数値文字参照のまま残す
				if (($ex->errorInfo[1] ?? null) != 1062)
					throw $ex;
			}
		}
	}

	/**
	 * コピーしたテーブルと入れ替え、作者・タグの集計や検索インデックスを作り直す
	 */
	function finish(): void
	{
		if (!$this->isConverting() || Meta::get($this->db, self::TABLE_META_NAME) !== "")
			throw new ApplicationException("コピーが終わっていません", 400);

		$tables = array_map(fn($x) => substr($x, 0, -strlen(self::NEW_SUFFIX)), $this->getTablesWithSuffix(self::NEW_SUFFIX));

		foreach ($this->getTablesWithSuffix(self::OLD_SUFFIX) as $i)
			$this->db->exec("drop table {$i}");

		if ($tables)
			$this->db->exec("rename table " . implode(", ", array_map(fn($x) => sprintf("%1\$s to %1\$s%2\$s, %1\$s%3\$s to %1\$s", $x, self::OLD_SUFFIX, self::NEW_SUFFIX), $tables)));

		foreach (array(App::META_TABLE, App::SESSION_STORE_TABLE) as $i)
			if ($this->hasTable($i))
				$this->db->exec(sprintf("alter table %s row_format=dynamic, convert to character set utf8mb4 collate %s", $i, MySQLDataStore::COLLATION));

		// 作者・タグの集計テーブルと検索インデックスは、utf8mb4 で作り直す
		foreach (array(App::AUTHOR_TABLE, App::TAG_TABLE, SQLiteSearchIndex::INDEX_TABLE, ClassicSearchIndex::INDEX_TABLE, SearchIndex::PHRASE_FORMAT_TABLE) as $i)
			$this->db->exec("drop table if exists {$i}");

		Meta::set($this->db, App::AUTHOR_TABLE, "1");
		Meta::set($this->db, App::TAG_TABLE, "1");
		$this->setProgress("", null, null);
		Meta::set($this->db, self::TABLE_META_NAME, null);
		$this->store->useFullUnicode($this->db);
		$this->store->forgetTables($this->db);

		ThreadEntry::ensureTable($this->db);
		SearchIndex::ensureTable($this->db);
		$this->store->setMaintenance($this->db, false);
	}

	private function hasTable(string $name): bool
	{
		$st = $this->db->prepare("select count(*) from information_schema.tables where table_schema = database() and table_name = ?");
		$st->execute(array($name));

		return (int)$st->fetchColumn() > 0;
	}

	/**
	 * 変換を中止し、作りかけのテーブルを削除して書き込みを再開する
	 */
	function abort(): void
	{
		foreach ($this->getTablesWithSuffix(self::NEW_SUFFIX) as $i)
			$this->db->exec("drop table {$i}");

		$this->setProgress("", null, null);
		Meta::set($this->db, self::TABLE_META_NAME, null);
		$this->store->setMaintenance($this->db, false);
	}

	/**
	 * 変換前のテーブルを削除する
	 */
	function dropOldTables(): void
	{
		if (!$this->store->isFullUnicode())
			throw new ApplicationException("変換が完了していません", 400);

		foreach ($this->getTablesWithSuffix(self::OLD_SUFFIX) as $i)
			$this->db->exec("drop table {$i}");
	}
}
?>
