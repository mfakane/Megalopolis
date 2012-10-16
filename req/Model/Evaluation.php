<?php
class Evaluation
{
	static $evaluationSchema = array
	(
		"entryID" => "bigint primary key not null",
		"id" => "bigint primary key not null",
		
		"point" => "integer",
		"host" => "varchar(512)",
		"dateTime" => "bigint",
	);
	
	public $entryID = 0;
	public $id = 0;
	public $point = 0;
	public $host = null;
	public $dateTime = 0;
	
	public $loaded = false;
	
	function __construct(PDO $db = null)
	{
		if ($db)
		{
			$this->id = time();
			$this->dateTime = time();
		}
	}
	
	/**
	 * @param int $entryID
	 * @return array of Evaluation
	 */
	static function getEvaluationsFromEntryID(PDO $db, $entryID)
	{
		$rt = array();
		
		foreach (self::query($db, sprintf
		('
			where entryID = %d',
			$entryID
		)) as $i)
		{
			$i->loaded = true;
			$rt[$i->id] = $i;
		}
		
		return $rt;
	}
	
	/**
	 * @param string $options [optional]
	 * @return array of ThreadEntry
	 */
	private static function query(PDO $db, $options = "")
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select * from %s
			%s',
			App::EVALUATION_TABLE,
			trim($options)
		)));
		Util::executeStatement($st);
		
		return $st->fetchAll(PDO::FETCH_CLASS, "Evaluation");
	}
	
	function save(PDO $db)
	{
		Util::saveToTable($db, $this, self::$evaluationSchema, App::EVALUATION_TABLE);
		$this->loaded = true;
	}
	
	function delete(PDO $db)
	{
		Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf
		('
			delete from %s
			where entryID = ? and id = ?',
			App::EVALUATION_TABLE
		))), array($this->entryID, $this->id));

		$this->loaded = false;
	}
	
	static function ensureTable(PDO $db)
	{
		$db->beginTransaction();
		Util::createTableIfNotExists($db, self::$evaluationSchema, App::EVALUATION_TABLE);
		$db->commit();
	}
}
?>