<?php
class MySQLSearchIndex extends SQLiteSearchIndex
{
	function __construct()
	{
		$this->gramLength = max(Configuration::$instance->mysqlSearchNgramLength, 2);
	}
	
	function registerThread(PDO $idb, Thread $thread, bool $removeExisting): void
	{
		if (!isset($thread->id, $thread->entry))
			return;

		/** @var array<string, string[]> */
		$words = array_filter(array
		(
			"title" => $this->getWords($thread->entry->title),
			"name" => $this->getWords($thread->entry->name),
			"summary" => $this->getWords($thread->entry->summary),
			"body" => Configuration::$instance->registerBodyToSearchIndex ? $this->getWords($thread->body) : null,
			"afterword" => $this->getWords($thread->afterword),
			"tag" => call_user_func_array(array("SearchIndex", "getWords"), $thread->entry->tags)
		));
		
		foreach ($words as $k => $v)
			$words[$k] = array_map(fn($_) => str_pad($_, $this->gramLength, "_"), $v);
		
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			insert into %s(docid, %s)
			values
			(
				%d,
				%s
			)
			on duplicate key update %s;',
			self::INDEX_TABLE,
			implode(", ", array_keys($words)),
			$thread->id,
			implode(", ", array_map(function($_) { return ":{$_}"; }, array_keys($words))),
			implode(", ", array_map(function($_) { return "{$_} = values({$_})"; }, array_keys($words)))
		)));
		Util::executeStatement($st, array_map(function($_) { return implode(" ", $_); }, $words));
	}
	
	function attachIndex(PDO $idb): void
	{
		if (!Configuration::$instance->dataStore instanceof MySQLDataStore)
			throw new ApplicationException("DataStore is not MySQLDataStore");

		Configuration::$instance->dataStore->attachFullTextIndex($idb, self::$searchIndexSchema, self::INDEX_TABLE);
	}
	
	function detachIndex(PDO $idb): void
	{
		if (!Configuration::$instance->dataStore instanceof MySQLDataStore)
			throw new ApplicationException("DataStore is not MySQLDataStore");

		Configuration::$instance->dataStore->detachFullTextIndex($idb, self::$searchIndexSchema, self::INDEX_TABLE);
	}
	
	function searchThread(PDO $idb, array $query, array $type = null, array $ids = null): array
	{
		if (!$query)
			return array();
		
		$queryArguments = array();
		
		foreach ($query as $i)
		{
			$prefix = "+";
			
			if (strpos($i, "-") === 0)
			{
				$i = substr($i, 1);
				$prefix = "-";
			}
			
			if ($words = $this->getWords(array("endOnIncompletedGram" => true, "noIncompletedGram" => mb_strlen($i) >= $this->gramLength), $i))
			{
				$currentWord = array();
				$lastLength = 0;
				
				foreach ($words as $j)
				{
					$currentLength = mb_strlen($j);
					
					if ($currentLength == $this->gramLength)
						$currentWord[] = $j;
					else
					{
						if ($currentWord)
						{
							$queryArguments[] = $prefix . '"' . implode(" ", $currentWord) . '"';
							$currentWord = array();
						}
						
						$queryArguments[] = $prefix . $j . (Configuration::$instance->mysqlSearchUseHeadMatching ? "*" : str_repeat("_", $this->gramLength - $currentLength));
					}
					
					$lastLength = $currentLength;
				}
				
				if ($currentWord)
					$queryArguments[] = $prefix . '"' . implode(" ", $currentWord) . '"';
			}
		}
		
		if (!($queryArguments = array_filter($queryArguments)))
			return array();
		
		$targetColumns = $type == null ? array_keys(self::$searchIndexSchema) : $type;
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			select docid from
			(' . implode(" union ", array_map(function($_) { return "select docid from %2\$s where match({$_}) against(? in boolean mode)"; }, $targetColumns)) . ') as search %s',
			is_array($ids) ? "where docid in (" . ($ids ? implode(", ", $ids) : -1) . ")" : "",
			self::INDEX_TABLE
		)));
		Util::executeStatement($st, array_fill(0, count($targetColumns), implode(" ", $queryArguments)));
		
		return $st?->fetchAll(PDO::FETCH_COLUMN, 0) ?? array();
	}
	
	function unregisterThread(PDO $idb, array $ids): void
	{
		$count = count($ids);
		
		if ($count == 1)
			Util::executeStatement(Util::ensureStatement($idb, $idb->prepare(sprintf
			('
				delete from %s
				where docid = ?
				limit %d',
				self::INDEX_TABLE,
				$count
			))), array($ids[0]));
		else
			Util::executeStatement(Util::ensureStatement($idb, $idb->prepare(sprintf
			('
				delete from %s
				where docid in (%s)
				limit %d',
				self::INDEX_TABLE,
				implode(", ", array_map('intval', $ids)),
				$count
			))));
	}
	
	function getEntryCount(PDO $idb): int
	{
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			select count(*) from %s',
			self::INDEX_TABLE
		)));
		Util::executeStatement($st);
		$rt = $st?->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0) ?? array(0);
		
		return intval(array_shift($rt));
	}
}
?>
