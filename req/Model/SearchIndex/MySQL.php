<?php
class MySQLSearchIndex extends SQLiteSearchIndex
{
	function __construct()
	{
		$this->gramLength = max(Configuration::$instance->mysqlSearchNgramLength, 2);
	}
	
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
		
		foreach ($words as $k => $v)
			$words[$k] = array_map(create_function('$_', 'return ($len = mb_strlen($_)) >= ' . $this->gramLength . ' ? $_ : $_ . str_repeat("_", ' . $this->gramLength . ' - $len);'), $v);
		
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
	
	function attachIndex(PDO $idb)
	{
		Configuration::$instance->dataStore->attachFullTextIndex($idb, self::$searchIndexSchema, self::INDEX_TABLE);
	}
	
	function detachIndex(PDO $idb)
	{
		Configuration::$instance->dataStore->detachFullTextIndex($idb, self::$searchIndexSchema, self::INDEX_TABLE);
	}
	
	function searchThread(PDO $idb, array $query, array $type = null, array $ids = null)
	{
		if (!$query)
			return array();
		
		$queryArguments = array();
		
		foreach ($query as $i)
			if ($words = $this->getWords(array("endOnIncompletedGram" => true), str_replace('"', "", $i)))
				$queryArguments[] = implode
				(
					" ",
					array_map(create_function('$_', 'return mb_strlen($_) >= ' . $this->gramLength . ' ? "+{$_}" : "+{$_}*";'), $words)
				);
		
		if (!($queryArguments = array_filter($queryArguments)))
			return array();
		
		$targetColumns = $type == null ? array_keys(self::$fullTextSearchIndexSchema) : $type;
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			select docid from
			(' . implode(" union ", array_map(create_function('$_', 'return "select docid from %2\$s where match({$_}) against(? in boolean mode)";'), $targetColumns)) . ') as search %s',
			is_array($ids) ? "where docid in (" . implode(", ", $ids) . ")" : null,
			self::INDEX_TABLE
		)));
		Util::executeStatement($st, array_fill(0, count($targetColumns), implode(" ", $queryArguments)));
		
		return $st->fetchAll(PDO::FETCH_COLUMN, 0);
	}
}
?>