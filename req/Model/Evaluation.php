<?php
namespace Megalopolis;

use \PDO;

class Evaluation
{
	static array $evaluationSchema = array
	(
		"entryID" => "bigint primary key not null",
		"id" => "bigint primary key not null",
		
		"point" => "integer",
		"host" => "varchar(512)",
		"dateTime" => "bigint",
	);
	
	public int $entryID = 0;
	public int $id = 0;
	public int $point = 0;
	public ?string $host = null;
	public int $dateTime = 0;
	
	public bool $loaded = false;
	
	function __construct(PDO $db = null)
	{
		if ($db)
		{
			$this->id = time();
			$this->dateTime = time();
		}
	}
	
	/**
	 * @return array<int, Evaluation>
	 */
	static function getEvaluationsFromEntryID(PDO $db, int $entryID): array
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
	 * @return Evaluation[]
	 */
	private static function query(PDO $db, string $options = ""): array
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select * from %s
			%s',
			App::EVALUATION_TABLE,
			trim($options)
		)));
		Util::executeStatement($st);
		
		return $st?->fetchAll(PDO::FETCH_CLASS, "\\Megalopolis\\Evaluation") ?? array();
	}
	
	function save(PDO $db): void
	{
		Util::saveToTable($db, $this, self::$evaluationSchema, App::EVALUATION_TABLE);
		$this->loaded = true;
	}
	
	function delete(PDO $db): void
	{
		Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf
		('
			delete from %s
			where entryID = ? and id = ?',
			App::EVALUATION_TABLE
		))), array($this->entryID, $this->id));

		$this->loaded = false;
	}
	
	static function ensureTable(PDO $db): void
	{
		$db->beginTransaction();
		Util::createTableIfNotExists($db, self::$evaluationSchema, App::EVALUATION_TABLE);
		$db->commit();
	}
}
?>
