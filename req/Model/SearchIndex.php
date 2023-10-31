<?php
abstract class SearchIndex
{
	static ?SearchIndex $instance;
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
	 * @param int $id
	 * @return int[]
	 */
	abstract function getExistingThread(PDO $idb): array;
	
	function getEntryCountCore(PDO $idb): ?int
	{
		return null;
	}
	
	function attachIndexCore(PDO $idb): void
	{
	}
	
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
	static function search(PDO $idb, array $query, array $type = null, array $ids = null): array
	{
		if (is_array($ids) && !$ids)
			return array();
		
		return self::ensureTable($idb)->searchThread($idb, $query, $type, $ids);
	}
	
	static function isUpgradeRequired(PDO $idb): bool
	{
		return self::getAvailableType() != "Classic" && Util::hasTable($idb, ClassicSearchIndex::INDEX_TABLE);
	}
	
	static function clear(PDO $idb): void
	{
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
			if (Configuration::$instance->dataStore->supportedFullTextSearchModule())
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
	 * @return string[]
	 */
	function getWords(): array
	{
		$rt = array();
		$gram = $this->gramLength;
		$gramMax = Configuration::$instance->maximumSearchIndexLength;
		$endOnIncompletedGram = false;
		$noIncompletedGram = false;
		
		foreach (func_get_args() as $i)
			if (is_array($i))
			{
				if (isset($i["endOnIncompletedGram"]))
					$endOnIncompletedGram = $i["endOnIncompletedGram"];
				
				if (isset($i["noIncompletedGram"]))
					$noIncompletedGram = $i["noIncompletedGram"];
			}
			else if (!Util::isEmpty($i))
				foreach (mb_split('[\x00-\x2F\x3A-\x40\x5B-\x60\x7B-\x7F' . self::$endchars . ']', $gramMax == -1 ? mb_strtolower($i) : mb_substr(mb_strtolower($i), 0, $gramMax)) as $j)
					if ($l = mb_strlen($j))
						for ($k = 0; $k < $l; $k++)
							if (!in_array($s = mb_substr($j, $k, $gram), $rt))
							{
								if ($noIncompletedGram && mb_strlen($s) < $gram)
									break;
								
								$rt[] = $s;
								
								if ($endOnIncompletedGram && mb_strlen($s) < $gram)
									break;
							}
		
		return $rt;
	}
}
?>
