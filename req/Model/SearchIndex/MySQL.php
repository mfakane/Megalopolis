<?php
class MySQLSearchIndex extends SQLiteSearchIndex
{
	function __construct()
	{
		$this->gramLength = 4;
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
			if ($words = $this->getWords(str_replace('"', "", $i)))
				$queryArguments[] = '+"' . implode(" ", $words) . '"';
		
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