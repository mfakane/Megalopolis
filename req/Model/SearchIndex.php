<?php
namespace Megalopolis;

use \PDO;

abstract class SearchIndex
{
	static ?SearchIndex $instance;
	/**
	 * 索引も検索語も n-gram の重複を除かない形式 (フレーズ検索で語の隣接を保つ) で作った索引に付ける目印。
	 * 目印の無い索引は以前の形式 (重複を除く) で作られているため、再生成されるまでは以前の形式で扱う
	 */
	const string PHRASE_FORMAT_TABLE = "searchIndexPhrase";
	protected bool $phraseFormat = true;
	private const string SEGMENT_SEPARATOR = '[\x00-\x2F\x3A-\x40\x5B-\x60\x7B-\x7F';
	static string $endchars = "　｛｝「」【】（）〈〉≪≫『』〔〕［］＜＞、。・…／＆！”＃＄％’ー＝＾～｜￥＋＊‘＠：；？＿";
	
	protected int $gramLength = 2;
	
	abstract function registerThread(PDO $idb, Thread $thread, bool $removeExisting): void;

	/**
	 * @param int[] $ids
	 */
	abstract function unregisterThread(PDO $idb, array $ids): void;

	/**
	 * @param string[] $query
	 * @param null|("title"|"name"|"summary"|"body"|"afterword"|"tag")[] $type
	 * @param ?int[] $ids
	 * @return int[]
	 */
	abstract function searchThread(PDO $idb, array $query, ?array $type = null, ?array $ids = null): array;

	abstract function ensureTableExists(PDO $idb): void;

	/**
	 * @return int[]
	 */
	abstract function getExistingThread(PDO $idb): array;
	
	function getEntryCountCore(PDO $idb): ?int
	{
		return null;
	}
	
	/** @api Legacy extension hook; subclasses may implement attachment. */
	function attachIndexCore(PDO $idb): void
	{
	}
	
	/** @api Legacy extension hook paired with attachIndexCore(). */
	function detachIndexCore(PDO $idb): void
	{
	}

	static function getEntryCount(PDO $idb): ?int
	{
		return self::ensureTable($idb)->getEntryCountCore($idb);
	}
	
	static function register(PDO $idb, Thread $thread, bool $removeExisting = true): void
	{
		self::ensureTable($idb)->registerThread($idb, $thread, $removeExisting);
	}
	
	/**
	 * @param int|int[] $ids
	 */
	static function unregister(PDO $idb, int|array $ids): void
	{
		self::ensureTable($idb)->unregisterThread($idb, is_array($ids) ? $ids : array($ids));
	}
	
	/**
	 * @param string[] $query
	 * @param null|("title"|"name"|"summary"|"body"|"afterword"|"tag")[] $type
	 * @param ?int[] $ids
	 * @return int[]
	 */
	static function search(PDO $idb, array $query, ?array $type = null, ?array $ids = null): array
	{
		if (is_array($ids) && !$ids)
			return array();
		
		return self::ensureTable($idb)->searchThread($idb, $query, $type, $ids);
	}
	
	/**
	 * 隣接を保った新しい形式か。古い形式の索引は再生成するまで検索が不正確になる
	 */
	function isPhraseFormat(): bool
	{
		return $this->phraseFormat;
	}

	static function isUpgradeRequired(PDO $idb): bool
	{
		return self::getAvailableType() != "Classic" && Util::hasTable($idb, ClassicSearchIndex::INDEX_TABLE);
	}
	
	static function clear(PDO $idb): void
	{
		if (Util::hasTable($idb, self::PHRASE_FORMAT_TABLE))
			Configuration::$instance->dataStore?->dropTable($idb, self::PHRASE_FORMAT_TABLE);
		
		if (Util::hasTable($idb, ClassicSearchIndex::INDEX_TABLE))
			Configuration::$instance->dataStore?->dropTable($idb, ClassicSearchIndex::INDEX_TABLE);
		
		if (Util::hasTable($idb, SQLiteSearchIndex::INDEX_TABLE))
			Configuration::$instance->dataStore?->dropTable($idb, SQLiteSearchIndex::INDEX_TABLE);
	}
	
	static function getAvailableType(): string
	{
		if (Configuration::$instance->dataStore instanceof MySQLDataStore)
			return "MySQL";
		else if (Configuration::$instance->dataStore instanceof SQLiteDataStore)
			if (Configuration::$instance->dataStore->supportedFullTextSearchModule() !== null)
				return "SQLite";
		
		return "Classic";
	}
	
	static function ensureTable(PDO $idb): SearchIndex
	{
		if (!isset(self::$instance))
			switch (Util::hasTable($idb, ClassicSearchIndex::INDEX_TABLE) ? "Classic" : self::getAvailableType())
			{
				case "MySQL":
					self::$instance = new MySQLSearchIndex();
					
					break;
				case "SQLite":
					self::$instance = new SQLiteSearchIndex();
					
					break;
				case "Classic":
					self::$instance = new ClassicSearchIndex();
					
					break;
				default:
					throw new ApplicationException("No SearchIndex type available");
			}
		
		self::$instance->ensureTableExists($idb);

		return self::$instance;
	}
	
	/**
	 * @return array{processed: int, remaining: int, count: int}
	 */
	static function registerSubject(PDO $db, PDO $idb, int $subject, ?int $offset = null, ?int $limit = null): array
	{
		if (($instance = self::$instance) === null) throw new ApplicationException("instance must be set");
		
		$existing = $instance->getExistingThread($idb);
		$entries = ThreadEntry::getEntryIDsBySubject($db, $subject);
		$slicedEntries = $offset === null || $limit === null ? $entries : array_slice($entries, $offset, $limit);
		$count = count($entries);
		$remaining = $offset === null || $limit === null ? $count : ($offset + $limit >= $count ? count($slicedEntries) : $count - $offset);
		$processed = 0;
		
		unset($entries);
		
		foreach ($slicedEntries as $i)
		{
			if (!in_array($i, $existing))
			{
				$thread = Thread::load($db, $i);
				
				if (!is_null($thread))
					$instance->registerThread($idb, $thread, false);
				
				unset($thread);
				
				$processed++;
			}
			
			$remaining--;
		}
		
		return array(
			"processed" => $processed,
			"remaining" => $remaining,
			"count" => $count
		);
	}

	/**
	 * @return array{unique: bool}
	 */
	protected function phraseWords(): array
	{
		return array("unique" => !$this->phraseFormat);
	}

	/**
	 * 索引のテーブルを作ったときは新しい形式の目印を付け、既にあるときは形式を調べる
	 */
	protected function ensurePhraseFormat(PDO $idb, bool $created): void
	{
		if ($created)
			Util::createTableIfNotExists($idb, array("version" => "integer primary key not null"), self::PHRASE_FORMAT_TABLE);

		$this->phraseFormat = $created || Util::hasTable($idb, self::PHRASE_FORMAT_TABLE);
	}

	/**
	 * 索引に登録するときと同じく、記号などで区切った部分に分ける
	 * @return string[]
	 */
	static function splitSegments(string $s): array
	{
		$segments = mb_split(self::SEGMENT_SEPARATOR . self::$endchars . ']', mb_strtolower($s));

		return $segments === false ? array() : array_values(array_filter($segments, fn($x) => $x !== ""));
	}

	/**
	 * unique を false にすると重複した n-gram も出現順に返す (フレーズ検索で隣接を保つため)
	 * @param (?string|array{endOnIncompletedGram?: bool, noIncompletedGram?: bool, unique?: bool})[] $strings
	 * @return string[]
	 */
	function getWords(array $strings): array
	{
		$rt = array();
		$gram = $this->gramLength;
		$gramMax = Configuration::$instance->maximumSearchIndexLength;
		$endOnIncompletedGram = false;
		$noIncompletedGram = false;
		$unique = true;
		
		foreach ($strings as $i)
			if (is_array($i))
			{
				if (isset($i["endOnIncompletedGram"]))
					$endOnIncompletedGram = $i["endOnIncompletedGram"];
				
				if (isset($i["noIncompletedGram"]))
					$noIncompletedGram = $i["noIncompletedGram"];
				
				if (isset($i["unique"]))
					$unique = $i["unique"];
			}
			else if (is_string($i) && mb_strlen($i) > 0)
				foreach ((array)mb_split(self::SEGMENT_SEPARATOR . self::$endchars . ']', $gramMax == -1 ? mb_strtolower($i) : mb_substr(mb_strtolower($i), 0, $gramMax)) as $j)
					if ($l = mb_strlen((string)$j))
						for ($k = 0; $k < $l; $k++)
						{
							$s = mb_substr((string)$j, $k, $gram);
							
							if ($unique && in_array($s, $rt))
								continue;
							
							if ($noIncompletedGram !== false && mb_strlen($s) < $gram)
								break;
							
							$rt[] = $s;
							
							if ($endOnIncompletedGram !== false && mb_strlen($s) < $gram)
								break;
						}
		
		return $rt;
	}
}
?>
