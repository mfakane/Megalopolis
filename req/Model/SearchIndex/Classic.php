<?php
class ClassicSearchIndex extends SearchIndex
{
	const INDEX_TABLE = "searchIndex";
	static $searchIndexSchema = array
	(
		"id" => "bigint",
		"type" => "varchar(127)",
		"word" => "varchar(127)"
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
			insert into %s(id, type, word)
			values
			(
				%d,
				?,
				?
			);',
			self::INDEX_TABLE,
			$thread->id
		)));

		foreach ($words as $k => $v)
			foreach ($v as $i)
				Util::executeStatement($st, array($k, $i));
	}
	
	/**
	 * @param int $id
	 */
	function unregisterThread(PDO $idb, $id)
	{
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			delete from %s
			where id = %d',
			self::INDEX_TABLE,
			$id
		)));
		Util::executeStatement($st);
	}
	
	function searchThread(PDO $idb, array $query, array $type = null, array $ids = null)
	{
		if (!$query)
			return array();
		
		if (!($query = call_user_func_array(array($this, "getWords"), $query)))
			return array();
		
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			select id, word from %s
			where word in (%s) %s %s',
			self::INDEX_TABLE,
			implode(", ", array_fill(0, count($query), "?")),
			$type ? "and type in (" . implode(",", array_map(create_function('$_', 'return "\'{$_}\'";'), $type)) . ")" : null,
			is_array($ids) ? "and id in (" . implode(",", $ids) . ")" : null
		)));
		Util::executeStatement($st, $query);
		$rt = array();
		
		foreach ($st->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_GROUP) as $k => $v)
			if (count(array_intersect($v, $query)) >= count($query))
				$rt[] = $k;
		
		return $rt;
	}
	
	function ensureTableExists(PDO $idb)
	{
		Util::createTableIfNotExists($idb, self::$searchIndexSchema, self::INDEX_TABLE);
	}
	
	/**
	 * @param int $id
	 * @return array|int
	 */
	function getExistingThread(PDO $idb)
	{
		$st = Util::ensureStatement($idb, $idb->prepare(sprintf
		('
			select id from %s
			group by id',
			self::INDEX_TABLE
		)));
		Util::executeStatement($st);
		
		return array_map("intval", $st->fetchAll(PDO::FETCH_COLUMN, 0));
	}
}
?>