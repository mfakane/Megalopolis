<?php
class SQLiteSearchIndex extends SearchIndex
{
	const INDEX_TABLE = "searchIndex2";
	static $searchIndexSchema = array
	(
		"docid" => "bigint primary key not null",
		"title" => "varchar(2048) fulltext",
		"name" => "varchar(2048) fulltext",
		"summary" => "mediumtext fulltext",
		"body" => "mediumtext fulltext",
		"afterword" => "mediumtext fulltext",
		"tag" => "varchar(2048) fulltext"
	);
	
	function registerThread(PDO $idb, Thread $thread, $removeExisting)
	{
		if ($removeExisting)
			self::unregister($idb, $thread->id);
		
		$words = array_filter(array
		(
			"title" => $this->getWords($thread->entry->title),
			"name" => $this->getWords($thread->entry->name),
			"summary" => $this->getWords($thread->entry->summary),
			"body" => Configuration::$instance->registerBodyToSearchIndex ? $this->getWords($thread->body) : null,
			"afterword" => $this->getWords($thread->afterword),
			"tag" => call_user_func_array(array("SearchIndex", "getWords"), $thread->entry->tags)
		));
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			insert into %s(docid, %s)
			values
			(
				%d,
				%s
			);',
			self::INDEX_TABLE,
			implode(", ", array_keys($words)),
			$thread->id,
			implode(", ", array_map(create_function('$_', 'return ":{$_}";'), array_keys($words)))
		)));
		Util::executeStatement($st, array_map(create_function('$_', 'return implode(" ", $_);'), $words));
	}
	
	function unregisterThread(PDO $idb, array $ids)
	{
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			delete from %s
			where docid in (%s)',
			self::INDEX_TABLE,
			implode(", ", array_map('intval', $ids))
		)));
		Util::executeStatement($st);
	}
	
	function searchThread(PDO $idb, array $query, array $type = null, array $ids = null)
	{
		if (!$query)
			return array();
		
		$queryArguments = array();
		
		foreach ($query as $i)
		{
			$prefix = "";
			
			if (strpos($i, "-") === 0)
			{
				$i = substr($i, 1);
				$prefix = "-";
			}
			
			if ($words = $this->getWords(array("endOnIncompletedGram" => true, "noIncompletedGram" => mb_strlen($i) >= $this->gramLength), $i))
			{
				$currentWord = array();
				
				foreach ($words as $j)
				{
					$currentLength = mb_strlen($j);
					
					if ($currentLength == $this->gramLength)
						$currentWord[] = $prefix . $j;
					else if (!$prefix)
						$currentWord[] = "{$j}*";
				}
				
				if ($currentWord)
					$queryArguments[] = '"' . implode(" ", $currentWord) . '"';
			}
		}
		
		if (!($queryArguments = array_filter($queryArguments)))
			return array();
		
		$targetColumns = $type == null ? array_keys(self::$searchIndexSchema) : $type;
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			select docid from
			(' . implode(" union ", array_map(create_function('$_', 'return "select docid from %2\$s where {$_} match ?";'), $targetColumns)) . ') %s',
			is_array($ids) ? "where docid in (" . ($ids ? implode(", ", $ids) : -1) . ")" : null,
			self::INDEX_TABLE
		)));
		
		Util::executeStatement($st, array_fill(0, count($targetColumns), implode(" ", $queryArguments)));
		
		return $st->fetchAll(PDO::FETCH_COLUMN, 0);
	}
	
	function ensureTableExists(PDO $idb)
	{
		$idb->beginTransaction();
		Util::createFullTextTableIfNotExists($idb, self::$searchIndexSchema, self::INDEX_TABLE);
		$idb->commit();
	}
	
	/**
	 * @param int $id
	 * @return array|int
	 */
	function getExistingThread(PDO $idb)
	{
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			select docid from %s
			group by docid',
			self::INDEX_TABLE
		)));
		Util::executeStatement($st);
		
		return array_map("intval", $st->fetchAll(PDO::FETCH_COLUMN, 0));
	}
}
?>