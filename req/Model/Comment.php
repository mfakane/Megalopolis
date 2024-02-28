<?php
namespace Megalopolis;

use \PDO;

class Comment
{
	static array $commentSchema = array
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
	
	public int $entryID = 0;
	public int $id = 0;
	public ?string $name = null;
	public ?string $mail = null;
	public ?string $body = null;
	public ?string $host = null;
	public int $dateTime = 0;
	
	public ?string $hash = null;
	
	public ?Evaluation $evaluation = null;
	
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
	 * @return array{id: int, name: ?string, mail: ?string, body: ?string, dateTime: int, evaluation: ?int}
	 */
	function toArray(): array
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
	 * @param ?Evaluation[] $evals
	 * @return array<int, Comment>
	 */
	static function getCommentsFromEntryID(PDO $db, int $entryID, $evals = null): array
	{
		$rt = array();
		
		if (is_null($evals))
			$evals = Evaluation::getEvaluationsFromEntryID($db, $entryID);
		
		foreach (self::query($db, $evals, sprintf
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
	 * @param array<int, Evaluation> $evals
	 * @return Comment[]
	 */
	private static function query(PDO $db, ?array $evals, string $options = ""): array
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select * from %s
			%s',
			App::COMMENT_TABLE,
			trim($options)
		)));
		Util::executeStatement($st);
		$rt = array();

		/** @var CommentEntity */
		foreach ($st?->fetchAll(PDO::FETCH_CLASS, "\\Megalopolis\\CommentEntity") ?? array() as $record)
		{
			$comment = new Comment();
			$comment->entryID = $record->entryID;
			$comment->id = $record->id;
			$comment->name = $record->name;
			$comment->mail = $record->mail;
			$comment->body = $record->body;
			$comment->host = $record->host;
			$comment->dateTime = $record->dateTime;
			$comment->hash = $record->hash;
			$comment->evaluation = isset($evals[$record->evaluation]) ? $evals[$record->evaluation] : null;
			$rt[] = $comment;
		}

		return $rt;
	}
	
	function save(PDO $db): void
	{
		if ($this->evaluation)
			$this->evaluation->save($db);
		
		$entity = new CommentEntity();
		$entity->entryID = $this->entryID;
		$entity->id = $this->id;
		$entity->name = $this->name;
		$entity->mail = $this->mail;
		$entity->body = $this->body;
		$entity->host = $this->host;
		$entity->dateTime = $this->dateTime;
		$entity->hash = $this->hash;
		$entity->evaluation = $this->evaluation?->id;

		Util::saveToTable($db, $entity, self::$commentSchema, App::COMMENT_TABLE);
		$this->loaded = true;
	}
	
	function delete(PDO $db): void
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
	
	static function ensureTable(PDO $db): void
	{
		$db->beginTransaction();
		Util::createTableIfNotExists($db, self::$commentSchema, App::COMMENT_TABLE);
		$db->commit();
	}
}

class CommentEntity {
	public int $entryID = 0;
	public int $id = 0;
	public ?string $name = null;
	public ?string $mail = null;
	public ?string $body = null;
	public ?string $host = null;
	public int $dateTime = 0;
	public ?string $hash = null;
	public ?int $evaluation = null;
}
?>
