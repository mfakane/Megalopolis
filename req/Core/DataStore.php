<?php

namespace Megalopolis;

use \PDO;
use \PDOStatement;

abstract class DataStore
{
	/** @var array<string, PDO> */
	protected array $handles = array();
	/** @var string[][] */
	protected array $tableNames = array();

	protected int $transactionCount = 0;

	/** @psalm-suppress PossiblyUnusedParam The PDO variable is retained by reference, not discarded. */
	protected function registerHandle(PDO &$db, string $name): void
	{
		$this->handles[$name] = &$db;
	}

	protected function unregisterHandle(PDO &$db): void
	{
		unset($this->handles[$this->getDatabaseNameByHandle($db)]);
	}

	protected function getDatabaseNameByHandle(PDO &$db): string|false
	{
		return array_search($db, $this->handles, true);
	}

	protected function getHandleByName(string $name): ?PDO
	{
		return $this->handles[$name] ?? null;
	}

	protected function registerTableByHandle(PDO &$db, string $name): void
	{
		$this->tableNames[$this->getDatabaseNameByHandle($db)][] = strtolower($name);
	}

	protected function unregisterTableByHandle(PDO &$db, string $name): void
	{
		$arr = $this->tableNames[$this->getDatabaseNameByHandle($db)];
		$index = array_search($name, $arr);

		if ($index === false)
			return;

		array_splice($arr, $index, 1);
		$this->tableNames[$this->getDatabaseNameByHandle($db)] = $arr;
	}

	function beginTransactionOrSavepoint(PDO &$db): void
	{
		if ($this->transactionCount == 0)
		{
			$db->beginTransaction();
			$this->transactionCount++;
		}
		else
		{
			$this->executeStatement($this->ensureStatement($db, $db->prepare("savepoint SP{$this->transactionCount}")));
			$this->transactionCount++;
		}
	}

	function commitOrReleaseSavepoint(PDO &$db): void
	{
		$this->transactionCount--;

		if ($this->transactionCount < 0)
			throw new ApplicationException("トランザクションが開始されていません");

		if ($this->transactionCount == 0)
			$db->commit();
		else
			$this->executeStatement($this->ensureStatement($db, $db->prepare("release savepoint SP{$this->transactionCount}")));
	}

	function rollBackOrToSavepoint(PDO &$db): void
	{
		$this->transactionCount--;
	
		if ($this->transactionCount < 0)
			throw new ApplicationException("トランザクションが開始されていません");

		if ($this->transactionCount == 0)
			$db->rollBack();
		else
			$this->executeStatement($this->ensureStatement($db, $db->prepare("rollback to savepoint SP{$this->transactionCount}")));
	}

	abstract function open(string $database = "data"): PDO;

	abstract function close(PDO &$db, bool $vacuum = false): void;

	/**
	 * @return string[]
	 */
	abstract function getTables(PDO $db);

	/**
	 * @param string $name
	 */
	function hasTable(PDO $db, $name): bool
	{
		$dbname = $this->getDatabaseNameByHandle($db);

		if (!isset($this->tableNames[$dbname]))
			$this->tableNames[$dbname] = array_map("strtolower", $this->getTables($db));

		return in_array(strtolower($name), $this->tableNames[$dbname]);
	}

	function ensureStatement(PDO $db, ?PDOStatement $st, bool $throw = true): ?PDOStatement
	{
		if ($st)
			return $st;
		else if ($throw) {
			$message = implode(":", $db->errorInfo());

			throw new ApplicationException($message);
		} else
			return null;
	}

	/**
	 * 保存できない文字があるデータストアで、値を保存できる形にする
	 */
	function encodeValue(mixed $value): mixed
	{
		return $value;
	}

	/**
	 * データベースの変換中など、書き込みを止めているか
	 */
	function isMaintenance(): bool
	{
		return false;
	}

	/**
	 * テーブルの作成・削除・名前の変更の後で、テーブルの一覧を読み直す
	 */
	function forgetTables(PDO $db): void
	{
		unset($this->tableNames[$this->getDatabaseNameByHandle($db)]);
	}

	function executeStatement(?PDOStatement $st, ?array $params = null, bool $throw = true): bool
	{
		if (!$st) return false;

		if (!is_null($params))
			$params = array_map(fn($x) => $this->encodeValue($x), $params);

		foreach (range(1, 5) as $_) {
			if (is_null($params))
				$rt = $st->execute();
			else
				$rt = $st->execute($params);

			if ($rt)
				break;

			$error = $st->errorInfo();

			if (
				$error != array("HY000", 5, "database is locked") &&
				$error != array("HY000", 21, "library routine called out of sequence")
			)
				break;

			usleep(5000);
		}

		$rt = $rt || $st->errorCode() == PDO::ERR_NONE;

		if ($rt)
			return $rt;
		else if ($throw) {
			$message = implode(":", $st->errorInfo());

			if (Configuration::$instance->debug)
				$message .= "\r\n" . $st->queryString;

			throw new ApplicationException($message);
		} else
			return false;
	}

	function createTableIfNotExists(PDO $db, array $schema, string $name, ?array $index = null): bool
	{
		if (!$this->hasTable($db, $name)) {
			$arr = array_map(fn($x, $y) => "{$x} {$y}", array_keys($schema), array_values($schema));

			$st = $this->ensureStatement($db, $db->prepare(strtr(sprintf(
				"
				create table if not exists %s
				(
					%s,
					primary key(%s)
				)",
				$name,
				implode(", ", array_map(fn($_) => strtr($_, array(" primary key" => "")), $arr)),
				implode(", ", array_map(fn($_) => explode(" ", $_)[0], array_filter($arr, fn($_) => mb_strstr($_, "primary key"))))
			), array(",
					primary key()" => ""))));

			$this->executeStatement($st);

			if (is_array($index))
				foreach ($index as $k => $v) {
					$st = $this->ensureStatement($db, $db->prepare(sprintf('create index if not exists %s on %s(%s)', $k, $name, is_array($v) ? implode(", ", $v) : $v)));
					$this->executeStatement($st);
				}

			$this->registerTableByHandle($db, $name);

			return true;
		}

		return false;
	}

	abstract function createFullTextTableIfNotExists(PDO $db, array $schema, string $name, string $indexSuffix = "Index"): bool;

	function dropTable(PDO $db, string $name): void
	{
		$this->executeStatement($this->ensureStatement($db, $db->prepare('drop table ' . $name)));
		$this->unregisterTableByHandle($db, $name);
	}

	/**
	 * @param array<string, string|int|float|null> $values
	 */
	function saveToTable(PDO $db, array $values, string $tableName): void
	{
		$st = $this->ensureStatement($db, $db->prepare(sprintf(
			'
			replace into %s
			(
				%s
			)
			values
			(
				%s
			)',
			$tableName,
			implode(", ", array_keys($values)),
			implode(", ", array_map(fn($k) => ":$k", array_keys($values)))
		)));
		if (!$st) return;

		foreach ($values as $k => $v)
			$st->bindValue(":$k", $this->encodeValue($v), is_null($v) ? PDO::PARAM_NULL : (is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR));

		$this->executeStatement($st);
	}

	function bindValues(PDOStatement $st, mixed $obj, array $schema): void
	{
		foreach ($schema as $k => $v) {
			$type = explode(" ", $v, 2);
			$type = $type[0];

			if (!property_exists($obj, $k))
				continue;

			$value = $obj->$k;

			if (is_null($value))
				$type = PDO::PARAM_NULL;
			else if (strpos($type, "int") !== false)
				$type = PDO::PARAM_INT;
			else if ($type === "bit")
				$type = PDO::PARAM_BOOL;
			else
				$type = PDO::PARAM_STR;

			if ($type === PDO::PARAM_BOOL)
				if ($value)
					$st->bindValue(":" . $k, 1, PDO::PARAM_INT);
				else
					$st->bindValue(":" . $k, null, PDO::PARAM_NULL);
			else
				$st->bindValue(":" . $k, $this->encodeValue($value), $type);
		}
	}
}

class SQLiteDataStore extends DataStore
{
	private string $directory;

	/** @var array<string, int> */
	private $handleOpenCount = array();

	const string MODULE_FTS3 = "fts3";
	const string MODULE_FTS4 = "fts4";

	function __construct(string $directory = Constant::DATA_DIR)
	{
		App::precondition(extension_loaded("pdo_sqlite"), "PDO SQLite");

		$this->directory = $directory;
	}

	#[\Override]
	function open(string $database = "data"): PDO
	{
		if (!isset($this->handleOpenCount[$database]))
			$this->handleOpenCount[$database] = 0;

		$this->handleOpenCount[$database]++;

		if ($rt = $this->getHandleByName($database))
			return $rt;

		$db = new PDO(sprintf("sqlite:%s%s.sqlite", rtrim($this->directory, "/") . "/", $database), null, null);
		$this->registerHandle($db, $database);

		Util::executeStatement(Util::ensureStatement($db, $db->prepare('pragma recursive_triggers = true;')));

		if ($database == App::INDEX_DATABASE)
			SearchIndex::ensureTable($db);
		else {
			Meta::ensureTable($db);
			Board::ensureTable($db);
		}

		return $db;
	}

	#[\Override]
	function close(PDO &$db, bool $vacuum = false): void
	{
		$name = $this->getDatabaseNameByHandle($db);

		if ($name === false || --$this->handleOpenCount[$name] > 0)
			return;

		unset($this->handleOpenCount[$name]);
		$this->unregisterHandle($db);

		if ($vacuum)
			$db->exec("vacuum");
	}

	/**
	 * @return string[]
	 */
	#[\Override]
	function getTables(PDO $db)
	{
		$st = $this->ensureStatement($db, $db->prepare("select name from sqlite_master where type = 'table'"));
		if (!$st) return array();

		$this->executeStatement($st, array());

		return $st->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
	}

	function alterTable(PDO $db, array $schema, string $name, ?array $index = null): void
	{
		$tempName = "{$name}Temp";
		$this->createTableIfNotExists($db, $schema, $tempName, $index);
		$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf('insert into %s select * from %s', $tempName, $name))));
		$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf('drop table %s', $name))));
		$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf('alter table %s rename to %s', $tempName, $name))));
	}

	#[\Override]
	function createFullTextTableIfNotExists(PDO $db, array $schema, string $name, string $indexSuffix = "Index"): bool
	{
		if (!$this->hasTable($db, $name)) {
			$module = $this->supportedFullTextSearchModule() ?? "";
			$arr = array_map(fn($x, $y) => "{$x} {$y}", array_keys($schema), array_values($schema));

			$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf(
				"
				create virtual table %s using %s
				(
					%s
				)",
				$name,
				$module,
				implode(", ", array_filter(array_map(fn($_) => strtr($_, array(" fulltext" => "")), $arr), fn($_) => strpos($_, "rowid") === false && strpos($_, "docid") === false))
			))));

			$this->registerTableByHandle($db, $name);

			return true;
		}

		return false;
	}

	function supportedFullTextSearchModule(): ?string
	{
		$db = new PDO("sqlite::memory:");
		$rt = null;

		foreach (array(self::MODULE_FTS4, self::MODULE_FTS3) as $i)
			if ($this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf('create virtual table temp using %s', $i))), array(), false)) {
				$rt = $i;

				break;
			}

		return $rt;
	}
}

class MySQLDataStore extends DataStore
{
	private int $openCount = 0;
	private ?string $host = null;
	private ?int $port = null;
	private string $databaseName;
	private ?string $unixSocket = null;
	private string $userName;
	private string $password;
	/** utf8mb4 で保存できるか。utf8 (3 バイト) のテーブルでは 4 バイトの文字を保存できない */
	private bool $fullUnicode = false;
	private bool $maintenance = false;

	const string CHARSET_META_NAME = "dataCharset";
	const string CHARSET_UTF8MB4 = "utf8mb4";
	const string MAINTENANCE_META_NAME = "maintenance";
	/** 大文字小文字などの区別は utf8 のテーブル (utf8_general_ci) と同じにする */
	const string COLLATION = "utf8mb4_general_ci";
	const string FULLTEXT_COLLATION = "utf8mb4_unicode_ci";

	/**
	 * @api Selected by user configuration rather than the default SQLite config.
	 * @param string|(string|int)[] $hostAndPortOrUnixSocket
	 */
	function __construct(string $databaseName, $hostAndPortOrUnixSocket, string $userName, string $password)
	{
		App::precondition(extension_loaded("pdo_mysql"), "PDO MySQL");

		$this->databaseName = $databaseName;

		if (is_array($hostAndPortOrUnixSocket))
			list($this->host, $this->port) = $hostAndPortOrUnixSocket;
		else
			$this->unixSocket = $hostAndPortOrUnixSocket;

		$this->userName = $userName;
		$this->password = $password;
	}

	#[\Override]
	function open(string $database = "data"): PDO
	{
		$this->openCount++;

		if ($database == App::INDEX_DATABASE)
			if ($rt = $this->getHandleByName("data"))
				return $rt;
			else {
				$this->openCount--;

				return $this->open("data");
			}

		$db = new PDO(
			sprintf("mysql:%s;dbname=%s;charset=utf8mb4", is_null($this->unixSocket) ? "host={$this->host};port={$this->port}" : "unixsocket={$this->unixSocket}", $this->databaseName),
			$this->userName,
			$this->password
		);
		$this->registerHandle($db, $database);

		// テーブルを utf8mb4 に変換するまでは、接続も utf8 にする
		$meta = $this->readMeta($db, array(self::CHARSET_META_NAME, self::MAINTENANCE_META_NAME));
		$this->fullUnicode = ($meta[self::CHARSET_META_NAME] ?? null) == self::CHARSET_UTF8MB4;
		$this->maintenance = ($meta[self::MAINTENANCE_META_NAME] ?? "") == "1";

		if (!$this->fullUnicode)
			$db->exec("set names utf8");

		Meta::ensureTable($db);
		Board::ensureTable($db);
		SearchIndex::ensureTable($db);

		return $db;
	}

	/**
	 * @param string[] $names
	 * @return array<string, ?string>
	 */
	private function readMeta(PDO $db, array $names): array
	{
		if (!$this->hasTable($db, App::META_TABLE))
			return array();

		$st = $db->prepare(sprintf("select name, value from %s where name in (%s)", App::META_TABLE, implode(", ", array_fill(0, count($names), "?"))));
		$st->execute($names);

		return $st->fetchAll(PDO::FETCH_KEY_PAIR);
	}

	function isFullUnicode(): bool
	{
		return $this->fullUnicode;
	}

	#[\Override]
	function isMaintenance(): bool
	{
		return $this->maintenance;
	}

	function setMaintenance(PDO $db, bool $maintenance): void
	{
		Meta::set($db, self::MAINTENANCE_META_NAME, $maintenance ? "1" : null);
		$this->maintenance = $maintenance;
	}

	/**
	 * テーブルを utf8mb4 に変換した後で、接続と以後に作るテーブルを utf8mb4 にする
	 */
	function useFullUnicode(PDO $db): void
	{
		$db->exec("set names utf8mb4 collate " . self::COLLATION);
		$this->fullUnicode = true;
		Meta::set($db, self::CHARSET_META_NAME, self::CHARSET_UTF8MB4);
	}

	/**
	 * utf8 のテーブルには 4 バイトの文字を保存できないため、数値文字参照にする。
	 * 出力時には実体参照をデコードするので、元の文字として表示される
	 */
	#[\Override]
	function encodeValue(mixed $value): mixed
	{
		if ($this->fullUnicode || !is_string($value))
			return $value;

		return preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', fn($m) => "&#" . (int)mb_ord($m[0], "UTF-8") . ";", $value) ?? $value;
	}

	#[\Override]
	function close(PDO &$db, bool $vacuum = false): void
	{
		if (--$this->openCount > 0)
			return;

		$this->unregisterHandle($db);
	}

	/**
	 * @return string[]
	 */
	#[\Override]
	function getTables(PDO $db)
	{
		$st = $this->ensureStatement($db, $db->prepare("show tables"));
		if (!$st) return array();

		$this->executeStatement($st, array());

		return $st->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
	}

	#[\Override]
	function createTableIfNotExists(PDO $db, array $schema, string $name, ?array $index = null): bool
	{
		if (!$this->hasTable($db, $name)) {
			$arr = array_map(fn($x, $y) => "{$x} {$y}", array_keys($schema), array_values($schema));

			$this->executeStatement($this->ensureStatement($db, $db->prepare(strtr(sprintf(
				"
				create table if not exists %s
				(
					%s,
					primary key(%s)%s
				)
				%s",
				$name,
				implode(", ", array_map(fn($_) => strtr($_, array(" primary key" => "")), $arr)),
				implode(", ", array_map(fn($_) => explode(" ", $_)[0], array_filter($arr, fn($_) => mb_strstr($_, "primary key")))),
				is_array($index) ? ", key " . implode(", key ", array_map(fn($x, $y) => "{$x}(" . (is_array($y) ? implode(", ", $y) : $y) . ")", array_keys($index), array_values($index))) : "",
				$this->fullUnicode
					? "default character set utf8mb4 collate " . self::COLLATION . " engine InnoDB row_format=dynamic"
					: "default character set utf8 engine InnoDB"
			), array(",
					primary key()" => "")))));

			$this->registerTableByHandle($db, $name);

			return true;
		}

		return false;
	}

	#[\Override]
	function createFullTextTableIfNotExists(PDO $db, array $schema, string $name, string $indexSuffix = "Index"): bool
	{
		if (!$this->hasTable($db, $name)) {
			$columns = array_map(fn($x, $y) => "{$x} " . strtr($y, array(" primary key" => "", " fulltext" => "")), array_keys($schema), array_values($schema));
			$primaryKeys = array_keys(array_filter($schema, fn($_) => strpos($_, "primary key") !== false));
			$fullTextIndices = array_keys(array_filter($schema, fn($_) => strpos($_, "fulltext") !== false));

			$this->executeStatement($this->ensureStatement($db, $db->prepare(strtr(sprintf(
				"
				create table if not exists %s
				(
					%s,
					primary key(%s)%s
				)
				%s",
				$name,
				implode(", ", $columns),
				implode(", ", $primaryKeys),
				", fulltext index " . implode(", fulltext index ", array_map(fn($x, $y) => "{$x}{$y}({$x})", $fullTextIndices, array_fill(0, count($fullTextIndices), $indexSuffix))),
				$this->fullUnicode
					? "default character set utf8mb4 collate " . self::FULLTEXT_COLLATION . " engine MyISAM"
					: "default character set utf8 collate utf8_unicode_ci engine MyISAM"
			), array(",
					primary key()" => "")))));

			$this->registerTableByHandle($db, $name);

			return true;
		}

		return false;
	}

	function attachFullTextIndex(PDO $db, array $schema, string $name, string $indexSuffix = "Index"): void
	{
		$fullTextIndices = array_keys(array_filter($schema, fn($_) => strpos($_, "fulltext") !== false));

		foreach ($fullTextIndices as $i)
			$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf(
				"
				create fulltext index %s on %s(%s)",
				$i . $indexSuffix,
				$name,
				$i
			))));
	}

	function detachFullTextIndex(PDO $db, array $schema, string $name, string $indexSuffix = "Index"): void
	{
		$fullTextIndices = array_keys(array_filter($schema, fn($_) => strpos($_, "fulltext") !== false));

		foreach ($fullTextIndices as $i)
			$this->executeStatement($this->ensureStatement($db, $db->prepare(sprintf(
				"
				drop index %s on %s",
				$i . $indexSuffix,
				$name
			))), array(), false);
	}
}

class DataStoreHandle
{
	private DataStore $store;
	private ?PDO $db;
	private bool $inLocalTransaction = false;

	function __construct(DataStore $store, PDO $db)
	{
		$this->store = $store;
		$this->db = $db;
	}

	/**
	 * @template T
	 * @param callable(PDO $db): T $callback
	 * @return T
	 */
	function withTransaction(callable $callback, bool $allowNested = false): mixed
	{
		if (!isset($this->db)) throw new ApplicationException("データベースが開かれていません");

		if ($this->db->inTransaction() && !$allowNested)
			throw new ApplicationException("既にトランザクションが開始されています");

		$this->store->beginTransactionOrSavepoint($this->db);
		try {
			$this->inLocalTransaction = true;

			$result = $callback($this->db);
			$this->store->commitOrReleaseSavepoint($this->db);
			$this->inLocalTransaction = false;

			return $result;
		} catch (\Throwable $e) {
			if ($this->db->inTransaction()) $this->store->rollBackOrToSavepoint($this->db);
			$this->inLocalTransaction = false;
			if ($e instanceof ApplicationException)
				throw $e;
			throw new ApplicationException($e->getMessage(), 500, $e);
		}
	}

	/**
	 * @template T
	 * @param DataStoreHandle $other
	 * @param callable(PDO $db, PDO $otherDb): T $callback
	 * @return T
	 */
	function withTransactionCombo(DataStoreHandle $other, callable $callback): mixed
	{
		return $this->withTransaction(
			fn($db) => $other->withTransaction(fn($odb) => $callback($db, $odb), true)
		);
	}

	/**
	 * @template T
	 * @param callable(PDO $db): T $callback
	 * @return T
	 */
	function execute(callable $callback): mixed
	{
		if (!isset($this->db)) throw new ApplicationException("データベースが開かれていません");

		return $callback($this->db);
	}

	/**
	 * @template T
	 * @param DataStoreHandle $other
	 * @param callable(PDO $db, PDO $otherDb): T $callback
	 * @return T
	 */
	function executeCombo(DataStoreHandle $other, callable $callback): mixed
	{
		return $this->execute(fn($db) => $other->execute(fn($odb) => $callback($db, $odb)));
	}

	function close(): void
	{
		if (!isset($this->db)) return;

		if ($this->inLocalTransaction)
			$this->store->rollBackOrToSavepoint($this->db);

		$this->store->close($this->db);
		unset($this->db);
	}

	function __destruct()
	{
		$this->close();
	}
}
