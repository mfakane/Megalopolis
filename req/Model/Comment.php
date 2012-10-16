<?php
class Comment
{
	static $commentSchema = array
	(
		"entryID" => "bigint primary key not null",
		"id" => "bigint primary key not null",
		
		"name" => "varchar(255)",
		"mail" => "varchar(255)",
		"body" => "mediumtext",
		"host" => "varchar(512)",
		"dateTime" => "bigint",
		"hash" => "varchar(512)",
		"evaluation" => "bigint"
	);
	
	public $entryID = 0;
	public $id = 0;
	public $name = null;
	public $mail = null;
	public $body = null;
	public $host = null;
	public $dateTime = 0;
	
	public $hash = null;
	
	/**
	 * @var Evaluation
	 */
	public $evaluation = null;
	
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
	 * @return array
	 */
	function toArray()
	{
		$c = &Configuration::$instance;
		
		return array
		(
			"id" => intval($this->id),
			"name" => $c->showName[Configuration::ON_COMMENT] ? $this->name : null,
			"mail" => $c->showName[Configuration::ON_COMMENT] ? $this->mail : null,
			"body" => $this->body,
			"dateTime" => intval($this->dateTime),
			"evaluation" => $c->showPoint[Configuration::ON_COMMENT] && $this->evaluation ? intval($this->evaluation->point) : null,
		);
	}
	
	/**
	 * @param int $entryID
	 * @return array of Comment
	 */
	static function getCommentsFromEntryID(PDO $db, $entryID, $evals = null)
	{
		$rt = array();
		
		if (is_null($evals))
			$evals = Evaluation::getEvaluationsFromEntryID($db, $entryID);
		
		foreach (self::query($db, sprintf
		('
			where entryID = %d',
			$entryID
		)) as $i)
		{
			$i->evaluation = isset($evals[$i->evaluation]) ? $evals[$i->evaluation] : null;
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
			App::COMMENT_TABLE,
			trim($options)
		)));
		Util::executeStatement($st);
		
		return $st->fetchAll(PDO::FETCH_CLASS, "Comment");
	}
	
	function save(PDO $db)
	{
		$ev = $this->evaluation;
		
		if ($this->evaluation)
		{
			$this->evaluation->save($db);
			$this->evaluation = $ev->id;
		}
		
		Util::saveToTable($db, $this, self::$commentSchema, App::COMMENT_TABLE);
		$this->loaded = true;
		$this->evaluation = $ev;
	}
	
	function delete(PDO $db)
	{
		Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf
		('
			delete from %s
			where entryID = ? and id = ?',
			App::COMMENT_TABLE
		))), array($this->entryID, $this->id));
		
		if ($this->evaluation)
			$this->evaluation->delete($db);
		
		$this->loaded = false;
	}
	
	static function ensureTable(PDO $db)
	{
		$db->beginTransaction();
		Util::createTableIfNotExists($db, self::$commentSchema, App::COMMENT_TABLE);
		$db->commit();
	}
}
?>