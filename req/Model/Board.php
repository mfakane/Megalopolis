<?php
class Board
{
	const ORDER_ASCEND = 0;
	const ORDER_DESCEND = 1;
	
	static array $subjectSchema = array
	(
		"id" => "integer primary key not null",
		
		"lastUpdate" => "bigint",
	);
	
	static ?int $latestSubject = null;
	
	static function getLatestSubject(PDO $db): int
	{
		if (isset(self::$latestSubject))
			return self::$latestSubject;
		
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select max(subject) from %s',
			App::THREAD_ENTRY_TABLE
		)));
		Util::executeStatement($st);
		$rt = $st?->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
		
		self::$latestSubject = $rt ? intval(array_pop($rt)) : 0;
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
			self::$latestSubject = max(self::$latestSubject, count(glob("Megalith/sub/subject*.txt")) - 1);
		
		return self::$latestSubject;
	}
	
	static function getEntryCount(PDO $db, PDO $idb): int
	{
		if (($rt = SearchIndex::$instance?->getEntryCount($idb)) !== null)
			return $rt;
		
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select count(subject) from %s',
			App::THREAD_ENTRY_TABLE
		)));
		Util::executeStatement($st);
		$rt = $st?->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0) ?? array(0);
		
		return intval(array_shift($rt));
	}
	
	static function getLastUpdate(PDO $db, int $subject): ?int
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select lastUpdate from %s where id = ?',
			App::SUBJECT_TABLE
		)));
		Util::executeStatement($st, array($subject));
		$rt = $st?->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
		
		return $rt ? intval(array_shift($rt)) : null;
	}
	
	static function setLastUpdate(PDO $db, int $subject): void
	{
		Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf
		('
			replace into %s(id, lastUpdate) values(?, ?)',
			App::SUBJECT_TABLE
		))), array($subject, time()));
	}
	
	static function ensureTable(PDO $db): void
	{
		Util::createTableIfNotExists($db, self::$subjectSchema, App::SUBJECT_TABLE);
		
		ThreadEntry::ensureTable($db);
		Thread::ensureTable($db);
		Comment::ensureTable($db);
		Evaluation::ensureTable($db);
	}
}
?>
