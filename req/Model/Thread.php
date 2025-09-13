<?php

namespace Megalopolis;

use \PDO;

class Thread
{
	const string REGEX_SPLIT_PAGE = "@<split\s*?/>@";
	const int WRITING_MODE_NOT_SPECIFIED = 0;
	const int WRITING_MODE_HORIZONTAL = 1;
	const int WRITING_MODE_VERTICAL = 2;

	static int $threadSchemaVersion = 2;
	static array $threadSchema = array(
		"id" => "bigint primary key not null",
		"subject" => "integer not null",

		"body" => "mediumtext",
		"afterword" => "mediumtext"
	);
	static int $threadStyleSchemaVersion = 3;
	static array $threadStyleSchema = array(
		"id" => "bigint primary key not null",

		"convertLineBreak" => "bit",
		"foreground" => "varchar(127)",
		"background" => "varchar(127)",
		"backgroundImage" => "varchar(512)",
		"border" => "varchar(127)",
		"writingMode" => "tinyint"
	);
	static array $threadPasswordSchema = array(
		"id" => "bigint primary key not null",

		"hash" => "varchar(512)"
	);

	public ThreadEntry $entry;

	/** @psalm-suppress PropertyNotSetInConstructor */
	public int $id {
		get => $this->entry->id;
	}

	/** @psalm-suppress PropertyNotSetInConstructor */
	public int $subject {
		get => $this->entry->subject;
	}

	public ?string $body = null;
	public ?string $afterword = null;

	public bool $convertLineBreak = true;
	public ?string $foreground = null;
	public ?string $background = null;
	public ?string $backgroundImage = null;
	public ?string $border = null;
	public int $writingMode = self::WRITING_MODE_NOT_SPECIFIED;

	public ?string $hash = null;

	/** @var Comment[] */
	public array $comments = array();
	/** @var Evaluation[] */
	public array $evaluations = array();
	/** @var Evaluation[] */
	public array $nonCommentEvaluations = array();

	public bool $loaded = false;

	function __construct(ThreadEntry &$entry)
	{
		$this->entry = &$entry;
	}

	/**
	 * @return array{
	 * 	entry: array,
	 * 	tags: ?string[],
	 * 	body: ?string,
	 * 	formattedBody: list<string>,
	 * 	afterword: ?string,
	 * 	formattedAfterword: string,
	 * 	convertLineBreak: bool,
	 * 	foreground: ?string,
	 * 	background: ?string,
	 * 	backgroundImage: ?string,
	 * 	border: ?string,
	 * 	writingMode: int,
	 * 	nonCommentEvaluation: ?int,
	 * 	comments: ?array
	 * }
	 */
	function toArray(): array
	{
		$formattedBody = array();

		for ($i = 0; $i < $this->pageCount(); $i++)
			$formattedBody[] = Visualizer::escapeBody($this, $i);

		return array(
			"entry" => $this->entry->toArray(),
			"tags" => Configuration::$instance->showTags[Configuration::ON_ENTRY] ? $this->entry->tags : null,
			"body" => $this->body,
			"formattedBody" => $formattedBody,
			"afterword" => $this->afterword,
			"formattedAfterword" => Visualizer::escapeAfterword($this),
			"convertLineBreak" => $this->convertLineBreak,
			"foreground" => $this->foreground,
			"background" => $this->background,
			"backgroundImage" => $this->backgroundImage,
			"border" => $this->border,
			"writingMode" => intval($this->writingMode),
			"nonCommentEvaluation" => Configuration::$instance->showPoint[Configuration::ON_ENTRY]
				? array_reduce($this->nonCommentEvaluations, fn(int $x, Evaluation $y) => $x + $y->point, 0)
				: null,
			"comments" => Configuration::$instance->showComment[Configuration::ON_ENTRY]
				? array_values(array_map(fn(Comment $_) => $_->toArray(), $this->comments))
				: null
		);
	}

	function pageCount(): int
	{
		if ($this->body === null)
			return 1;

		$matches = array();

		return (int)preg_match_all(self::REGEX_SPLIT_PAGE, $this->body, $matches) + 1;
	}

	function page(int $page): ?string
	{
		if ($this->body === null)
			return null;

		$rt = preg_split(self::REGEX_SPLIT_PAGE, $this->body);

		return is_array($rt) && isset($rt[$page - 1]) ? $rt[$page - 1] : null;
	}

	function evaluate(PDO $db, int $point, bool $saveThread = true): Evaluation
	{
		$eval = Evaluation::forEntry($this->entry);
		$eval->point = $point;
		$eval->host = Util::getRemoteHost();
		$eval->save($db);
		$this->nonCommentEvaluations[$eval->id] = $this->evaluations[$eval->id] = $eval;
		$this->entry->responseLastUpdate = time();
		$this->entry->updateCount($this);

		if ($saveThread)
			$this->save($db);

		return $eval;
	}

	function unevaluate(PDO $db, Evaluation $eval): void
	{
		unset($this->evaluations[$eval->id]);
		unset($this->nonCommentEvaluations[$eval->id]);

		$eval->delete($db);

		foreach ($this->comments as $i)
			if ($i->evaluation && $i->evaluation->id === $eval->id) {
				$i->evaluation = null;
				$i->save($db);
			}

		$this->entry->responseLastUpdate = time();
		$this->entry->updateCount($this);
		$this->save($db);
	}

	function comment(PDO $db, string $name, string $mail, string $body, string $password, int $point, bool $saveThread = true): Comment
	{
		$comment = Comment::forEntry($this->entry);
		$comment->name = $name;
		$comment->mail = $mail;
		$comment->body = $body;
		$comment->hash = Util::hash($password);
		$comment->host = Util::getRemoteHost();

		if ($point) {
			$eval = Evaluation::forComment($comment);
			$eval->point = $point;
			$comment->evaluation = &$eval;
			$this->evaluations[$eval->id] = &$eval;
		}

		$comment->save($db);
		$this->comments[$comment->id] = $comment;
		$this->entry->responseLastUpdate = time();
		$this->entry->updateCount($this);

		if ($saveThread)
			$this->save($db);

		return $comment;
	}

	function uncomment(PDO $db, Comment $comment): void
	{
		unset($this->comments[$comment->id]);

		if ($comment->evaluation)
			unset($this->evaluations[$comment->evaluation->id]);

		$comment->delete($db);
		$this->entry->responseLastUpdate = time();
		$this->entry->updateCount($this);
		$this->save($db);
	}

	function delete(PDO $db, PDO $idb): void
	{
		$this->entry->delete($db, $idb);
		$this->loaded = false;
	}

	function getCommentByID(PDO $db, int $id): ?Comment
	{
		$rt = array_filter($this->comments, fn(Comment $_) => $_->id === $id);

		if ($rt)
			return array_pop($rt);
		else
			return null;
	}

	function getEvaluationByID(PDO $db, int $id): ?Evaluation
	{
		$rt = array_filter($this->nonCommentEvaluations, fn(Evaluation $_) => $_->id === $id);

		if ($rt)
			return array_pop($rt);
		else
			return null;
	}

	static function load(PDO $db, int $id): ?Thread
	{
		$rt = self::query($db, sprintf(
			'
			where %s.id = %d',
			App::THREAD_TABLE,
			$id
		));
		$entry = ThreadEntry::load($db, $id);

		if ($rt && $entry) {
			$rt = $rt[0];
			$rt->entry = $entry;
			$rt->loaded = true;
			$rt->evaluations = Evaluation::getEvaluationsFromEntryID($db, $rt->entry->id);
			$rt->comments = Comment::getCommentsFromEntryID($db, $rt->entry->id, $rt->evaluations);
			$resEval = array_map(fn(Comment $_) => $_->evaluation?->id, $rt->comments);
			$rt->nonCommentEvaluations = array();

			foreach ($rt->evaluations as $i)
				if (!in_array($i->id, $resEval))
					$rt->nonCommentEvaluations[$i->id] = $i;

			return $rt;
		} else
			return null;
	}

	static function loadWithMegalith(PDO $db, PDO $idb, int $id): ?Thread
	{
		if (!($rt = Thread::load($db, $id)))
			if (
				Configuration::$instance->convertOnDemand &&
				is_dir("Megalith/sub") &&
				is_file($path = "Megalith/dat/{$id}.dat")
			) {
				$subject = 0;
				$files = glob("Megalith/sub/subject*.txt");

				// $id が含まれてる作品集を特定する
				foreach (($files !== false ? $files : []) as $i) {
					$basename = basename($i);

					// subjects.txt (作品集一覧) は除外
					if ($basename === "subjects.txt") continue;

					// $id が含まれているか確認
					$content = file_get_contents($i);
					if ($content === false || strpos($content, "{$id}.dat") === false) continue;

					if ($basename === "subject.txt")
						$subject = Board::getLatestSubject($db);
					else
						$subject = intval(strtr($basename, array(
							"subject" => "",
							".txt" => ""
						)));

					break;
				}

				$rt = Util::convertAndSaveToThread($db, $idb, $subject, $path, "Megalith/com/{$id}.res.dat", "Megalith/aft/{$id}.aft.dat");
			}

		return $rt;
	}

	function save(PDO $db, bool $setSubjectLastUpdate = true): void
	{
		$this->entry->save($db, $setSubjectLastUpdate);

		$threadEntity = [
			"id" => $this->entry->id,
			"subject" => $this->entry->subject,
			"body" => $this->body,
			"afterword" => $this->afterword
		];
		Util::saveToTable($db, $threadEntity, App::THREAD_TABLE);

		$threadStyleEntity = [
			"id" => $this->entry->id,
			"convertLineBreak" => $this->convertLineBreak ? 1 : 0,
			"foreground" => $this->foreground,
			"background" => $this->background,
			"backgroundImage" => $this->backgroundImage,
			"border" => $this->border,
			"writingMode" => $this->writingMode
		];
		Util::saveToTable($db, $threadStyleEntity, App::THREAD_STYLE_TABLE);

		$threadPasswordEntity = [
			"id" => $this->entry->id,
			"hash" => $this->hash
		];
		Util::saveToTable($db, $threadPasswordEntity, App::THREAD_PASSWORD_TABLE);
		
		$this->loaded = true;
	}

	static function ensureTable(PDO $db): void
	{
		if (Util::hasTable($db, App::THREAD_TABLE)) {
			$currentThreadSchemaVersion = intval(Meta::get($db, App::THREAD_TABLE, "1"));

			if ($currentThreadSchemaVersion < 2)
				if (Configuration::$instance->dataStore instanceof SQLiteDataStore)
					Configuration::$instance->dataStore->alterTable($db, self::$threadSchema, App::THREAD_TABLE);
				else
					Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf('alter table %s drop primary key, add primary key(id)', App::THREAD_TABLE))));
		}

		if (Util::hasTable($db, App::THREAD_STYLE_TABLE)) {
			$currentThreadStyleSchemaVersion = intval(Meta::get($db, App::THREAD_STYLE_TABLE, "1"));

			if ($currentThreadStyleSchemaVersion < 2)
				Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf('alter table %s add column border varchar(127)', App::THREAD_STYLE_TABLE))));

			if ($currentThreadStyleSchemaVersion < 3)
				Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf('alter table %s add column writingMode tinyint', App::THREAD_STYLE_TABLE))));
		}

		Util::createTableIfNotExists($db, self::$threadSchema, App::THREAD_TABLE);
		Util::createTableIfNotExists($db, self::$threadStyleSchema, App::THREAD_STYLE_TABLE);
		Util::createTableIfNotExists($db, self::$threadPasswordSchema, App::THREAD_PASSWORD_TABLE);
		Meta::set($db, App::THREAD_STYLE_TABLE, strval(self::$threadStyleSchemaVersion));
		Meta::set($db, App::THREAD_TABLE, strval(self::$threadSchemaVersion));
	}

	/**
	 * @return Thread[]
	 */
	private static function query(PDO $db, string $options): array
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf(
			'
			select * from %1$s
			left join %2$s on %1$s.id = %2$s.id
			left join %3$s on %1$s.id = %3$s.id
			%4$s',
			App::THREAD_TABLE,
			App::THREAD_STYLE_TABLE,
			App::THREAD_PASSWORD_TABLE,
			trim($options)
		)));
		Util::executeStatement($st);

		if ($st === null) return [];

		/** @param array{
		 * id: int,
		 * subject: string,
		 * body: string,
		 * afterword: string,
		 * convertLineBreak: int,
		 * foreground: ?string,
		 * background: ?string,
		 * backgroundImage: ?string,
		 * border: ?string,
		 * writingMode: int
		 * } $record */
		return array_map(function (array $record): Thread {
			$entry = new ThreadEntry($record['id']);
			$thread = new Thread($entry);
			$thread->entry->subject = $record['subject'];
			$thread->body = $record['body'];
			$thread->afterword = $record['afterword'];
			$thread->convertLineBreak = (bool) $record['convertLineBreak'];
			$thread->foreground = $record['foreground'];
			$thread->background = $record['background'];
			$thread->backgroundImage = $record['backgroundImage'];
			$thread->border = $record['border'];
			$thread->writingMode = $record['writingMode'];
			return $thread;
		}, $st->fetchAll());
	}

	/**
	 * @return Thread[]
	 */
	static function getThreadsBySubject(PDO $db, int $subject, int $order = Board::ORDER_DESCEND): array
	{
		$entries = ThreadEntry::getEntriesBySubject($db, $subject, $order);
		$entriesById = array_combine(array_map(fn(ThreadEntry $entry) => $entry->id, $entries), $entries);
		$threads = self::query($db, sprintf(
			'
			where %1$s.subject = %2$d
			order by %1$s.id %3$s',
			App::THREAD_TABLE,
			$subject,
			$order == Board::ORDER_ASCEND ? "asc" : "desc"
		));

		foreach ($threads as $thread) {
			$thread->entry = $entriesById[$thread->id];
			$thread->loaded = true;
		}

		return $threads;
	}
}

class ThreadEntity
{
	public int $id = 0;
	public int $subject = 0;
	public ?string $body = null;
	public ?string $afterword = null;
	public int $convertLineBreak = 1;
	public ?string $foreground = null;
	public ?string $background = null;
	public ?string $backgroundImage = null;
	public ?string $border = null;
	public int $writingMode = Thread::WRITING_MODE_NOT_SPECIFIED;
}
