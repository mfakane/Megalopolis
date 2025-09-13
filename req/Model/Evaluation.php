<?php

namespace Megalopolis;

use \PDO;

class Evaluation
{
	static array $evaluationSchema = array(
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

	function __construct(int $id)
	{
		$this->id = $id;
	}

	/**
	 * @param array{
	 * entryID: int,
	 * id: int,
	 * point?: ?int,
	 * host?: ?string,
	 * dateTime?: int,
	 * } $data
	 */
	static function fromArray(array $data): Evaluation
	{
		$evaluation = new Evaluation($data["id"]);
		$evaluation->entryID = $data["entryID"];

		if (isset($data["point"])) $evaluation->point = $data["point"];
		if (isset($data["host"])) $evaluation->host = $data["host"];
		if (isset($data["dateTime"])) $evaluation->dateTime = $data["dateTime"];

		return $evaluation;
	}

	static function forComment(Comment &$comment): Evaluation
	{
		$evaluation = new Evaluation($comment->id);
		$evaluation->entryID = $comment->entryID;
		$evaluation->point = $comment->evaluation ? $comment->evaluation->point : 0;
		$evaluation->host = $comment->host;
		$evaluation->dateTime = $comment->dateTime;

		return $evaluation;
	}

	static function forEntry(ThreadEntry &$entry): Evaluation
	{
		$id = time();
		$evaluation = new Evaluation($id);
		$evaluation->dateTime = $id;
		$evaluation->entryID = $entry->id;

		return $evaluation;
	}

	/**
	 * @return array<int, Evaluation>
	 */
	static function getEvaluationsFromEntryID(PDO $db, int $entryID): array
	{
		$rt = array();

		foreach (
			self::query($db, sprintf(
				'
			where entryID = %d',
				$entryID
			)) as $i
		) {
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
		$st = Util::ensureStatement($db, $db->prepare(sprintf(
			'
			select * from %s
			%s',
			App::EVALUATION_TABLE,
			trim($options)
		)));
		Util::executeStatement($st);

		/** @var Evaluation[] */
		$rt = [];

		foreach ($st?->fetchAll() ?? [] as $record) {
			$rt[] = self::fromArray($record);
		}

		return $rt;
	}

	function save(PDO $db): void
	{
		$entity = [
			"entryID" => $this->entryID,
			"id" => $this->id,
			"point" => $this->point,
			"host" => $this->host,
			"dateTime" => $this->dateTime,
		];
		Util::saveToTable($db, $entity, App::EVALUATION_TABLE);
		
		$this->loaded = true;
	}

	function delete(PDO $db): void
	{
		Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf(
			'
			delete from %s
			where entryID = ? and id = ?',
			App::EVALUATION_TABLE
		))), array($this->entryID, $this->id));

		$this->loaded = false;
	}

	static function ensureTable(PDO $db): void
	{
		Util::createTableIfNotExists($db, self::$evaluationSchema, App::EVALUATION_TABLE);
	}
}
