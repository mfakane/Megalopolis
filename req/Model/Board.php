<?php
class Board
{
	const ORDER_ASCEND = 0;
	const ORDER_DESCEND = 1;
	
	static $latestSubject = null;
	
	/**
	 * @return int
	 */
	static function getLatestSubject(PDO $db)
	{
		if (self::$latestSubject)
			return self::$latestSubject;
		
		$rt = Util::ensureStatement($db, $db->query(sprintf
		('
			select max(subject) from %s',
			App::THREAD_ENTRY_TABLE
		)))->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
		
		self::$latestSubject = $rt ? array_pop($rt) : 0;
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
			self::$latestSubject = max(self::$latestSubject, count(glob("Megalith/sub/subject*.txt")) - 1);
		
		return intval(self::$latestSubject);
	}
	
	/**
	 * @return int
	 */
	static function getSubjectCount(PDO $db)
	{
		$rt = Util::ensureStatement($db, $db->query(sprintf
		('
			select count(distinct subject) from %s
			limit 1',
			App::THREAD_ENTRY_TABLE
		)))->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
		
		$rt = $rt ? array_pop($rt) : 0;
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
			$rt = self::$latestSubject ? self::$latestSubject : self::$latestSubject = max($rt, count(glob("Megalith/sub/subject*.txt")) - 1);
		
		return intval($rt);
	}
	
	static function ensureTable(PDO $db)
	{
		ThreadEntry::ensureTable($db);
		Thread::ensureTable($db);
		Comment::ensureTable($db);
		Evaluation::ensureTable($db);
	}
}
?>