<?php
abstract class SearchIndex
{
	/**
	 * @var SearchIndex
	 */
	static $instance;
	static $endchars = "　｛｝「」【】（）〈〉≪≫『』〔〕［］＜＞、。・…／＆！”＃＄％’ー＝＾～｜￥＋＊‘＠：；？＿";
	
	/**
	 * @var int
	 */
	protected $gramLength = 2;
	
	abstract function registerThread(PDO $idb, Thread $thread);
	/**
	 * @param int $id
	 */
	abstract function unregisterThread(PDO $idb, $id);
	abstract function searchThread(PDO $idb, array $query, array $type = null, array $ids = null);
	abstract function ensureTableExists(PDO $idb);
	/**
	 * @param int $id
	 * @return array|int
	 */
	abstract function getExistingThread(PDO $idb);
	
	function attachIndex(PDO $idb)
	{
	}
	
	function detachIndex(PDO $idb)
	{
	}
	
	static function register(PDO $idb, Thread $thread)
	{
		self::$instance->registerThread($idb, $thread);
	}
	
	/**
	 * @param int $id
	 */
	static function unregister(PDO $idb, $id)
	{
		self::$instance->unregisterThread($idb, $id);
	}
	
	/**
	 * @return array|int
	 */
	static function search(PDO $idb, array $query, array $type = null, array $ids = null)
	{
		return self::$instance->searchThread($idb, $query, $type, $ids);
	}
	
	static function isUpgradeRequired(PDO $idb)
	{
		return self::getAvailableType() != "Classic" && Util::hasTable($idb, ClassicSearchIndex::INDEX_TABLE);
	}
	
	static function clear(PDO $idb)
	{
		if (Util::hasTable($idb, ClassicSearchIndex::INDEX_TABLE))
			Configuration::$instance->dataStore->dropTable($idb, ClassicSearchIndex::INDEX_TABLE);
		
		if (Util::hasTable($idb, SQLiteSearchIndex::INDEX_TABLE))
			Configuration::$instance->dataStore->dropTable($idb, SQLiteSearchIndex::INDEX_TABLE);
	}
	
	static function getAvailableType()
	{
		if (Configuration::$instance->dataStore instanceof MySQLDataStore)
			return "MySQL";
		else if (Configuration::$instance->dataStore instanceof SQLiteDataStore)
			if (Configuration::$instance->dataStore->supportedFullTextSearchModule())
				return "SQLite";
		
		return "Classic";
	}
	
	static function ensureTable(PDO $idb)
	{
		if (self::$instance == null)
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
			}
		
		self::$instance->ensureTableExists($idb);
	}
	
	/**
	 * @param int $subject
	 * @param int $offset [optional]
	 * @param int $limit [optional]
	 * @return array processed, remaining, count
	 */
	static function registerSubject(PDO $db, PDO $idb, $subject, $offset = 0, $limit = 0)
	{
		$instance = self::$instance;
		
		$existing = $instance->getExistingThread($idb);
		$entries = ThreadEntry::getEntryIDsBySubject($db, $subject);
		$slicedEntries = $limit == 0 ? $entries : array_slice($entries, $offset, $limit);
		$count = count($entries);
		$remaining = $limit == 0 ? $count : ($offset + $limit >= $count ? count($slicedEntries) : $count - $offset);
		$processed = 0;
		
		unset($entries);
		
		foreach ($slicedEntries as $i)
		{
			if (!in_array($i, $existing))
			{
				$thread = Thread::load($db, $i);
				
				if (!is_null($thread))
					$instance->registerThread($idb, $thread);
				
				unset($thread);
				
				$processed++;
			}
			
			$remaining--;
		}
		
		return array($processed, $remaining, $count);
	}
	
	function getWords()
	{
		$rt = array();
		$gram = $this->gramLength;
		$gramMax = Configuration::$instance->maximumSearchIndexLength;
		
		foreach (func_get_args() as $i)
			if (!Util::isEmpty($i))
				foreach (mb_split('[\x00-\x2F\x3A-\x40\x5B-\x60\x7B-\x7F' . self::$endchars . ']', $gramMax == -1 ? mb_strtolower($i) : mb_substr(mb_strtolower($i), 0, $gramMax)) as $j)
					if ($l = mb_strlen($j))
						for ($k = 0; $k < $l; $k++)
							if (!in_array($s = mb_substr($j, $k, $gram), $rt))
								$rt[] = $s;
		
		return $rt;
	}
}
?>