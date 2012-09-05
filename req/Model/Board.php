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
		
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select max(subject) from %s',
			App::THREAD_ENTRY_TABLE
		)));
		Util::executeStatement($st);
		$rt = $st->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
		
		self::$latestSubject = $rt ? intval(array_pop($rt)) : 0;
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
			self::$latestSubject = max(self::$latestSubject, count(glob("Megalith/sub/subject*.txt")) - 1);
		
		return self::$latestSubject;
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