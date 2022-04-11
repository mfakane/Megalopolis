<?php
class Thread
{
	const REGEX_SPLIT_PAGE = "@<split\s*?/>@";
	const WRITING_MODE_NOT_SPECIFIED = 0;
	const WRITING_MODE_HORIZONTAL = 1;
	const WRITING_MODE_VERTICAL = 2;
	
	static int $threadSchemaVersion = 2;
	static array $threadSchema = array
	(
		"id" => "bigint primary key not null",
		"subject" => "integer not null",
		
		"body" => "mediumtext",
		"afterword" => "mediumtext"
	);
	static int $threadStyleSchemaVersion = 3;
	static array $threadStyleSchema = array
	(
		"id" => "bigint primary key not null",

		"convertLineBreak" => "bit",
		"foreground" => "varchar(127)",
		"background" => "varchar(127)",
		"backgroundImage" => "varchar(512)",
		"border" => "varchar(127)",
		"writingMode" => "tinyint"
	);
	static array $threadPasswordSchema = array
	(
		"id" => "bigint primary key not null",
	
		"hash" => "varchar(512)"
	);
	
	public ?ThreadEntry $entry = null;
	
	public ?int $id = 0;
	public ?int $subject = 0;
	
	public ?string $body = null;
	public ?string $afterword = null;
	
	public ?bool $convertLineBreak = true;
	public ?string $foreground = null;
	public ?string $background = null;
	public ?string $backgroundImage = null;
	public ?string $border = null;
	public $writingMode = self::WRITING_MODE_NOT_SPECIFIED;
	
	public ?string $hash = null;
	
	/** @var Comment[] */
	public array $comments = array();
	/** @var Evaluation[] */
	public array $evaluations = array();
	/** @var Evaluation[] */
	public array $nonCommentEvaluations = array();
	
	public bool $loaded = false;
	
	function __construct(PDO $db = null)
	{
		$this->entry = new ThreadEntry($db);
		$this->updatePropertyLink();
	}
	
	/**
	 * @return array<string, mixed>
	 */
	function toArray(): array
	{
		$formattedBody = array();
		
		for ($i = 0; $i < $this->pageCount(); $i++)
			$formattedBody[] = Visualizer::escapeBody($this, $i);
		
		return array
		(
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
			"nonCommentEvaluation" => Configuration::$instance->showPoint[Configuration::ON_ENTRY] ? array_reduce($this->nonCommentEvaluations, function($x, $y) { return $x + $y->point; }, 0) : null,
			"comments" => Configuration::$instance->showComment[Configuration::ON_ENTRY] ? array_values(array_map(function($_) { return $_->toArray(); }, $this->comments)) : null
		);
	}
	
	function pageCount(): int
	{
		if ($this->body === null) return 1;

		$matches = array();

		return preg_match_all(self::REGEX_SPLIT_PAGE, $this->body, $matches) + 1;
	}
	
	function page(int $page): ?string
	{
		if ($this->body === null) return null;

		$rt = preg_split(self::REGEX_SPLIT_PAGE, $this->body);
		
		return isset($rt[$page - 1]) ? $rt[$page - 1] : null;
	}
	
	function updatePropertyLink(): void
	{
		if ($this->entry === null) throw new ApplicationException("Thread entry not set");

		$this->id = &$this->entry->id;
		$this->subject = &$this->entry->subject;
	}
	
	function evaluate(PDO $db, int $point, bool $saveThread = true): Evaluation
	{
		if ($this->id === null) throw new ApplicationException("Thread id not set");
		if ($this->entry === null) throw new ApplicationException("Thread entry not set");

		$eval = new Evaluation($db);
		$eval->entryID = $this->id;
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
		if ($this->entry === null) throw new ApplicationException("Thread entry not set");

		unset($this->evaluations[$eval->id]);
		unset($this->nonCommentEvaluations[$eval->id]);
		
		$eval->delete($db);
		
		foreach ($this->comments as $i)
			if ($i->evaluation && $i->evaluation->id === $eval->id)
			{
				$i->evaluation = null;
				$i->save($db);
			}
		
		$this->entry->responseLastUpdate = time();
		$this->entry->updateCount($this);
		$this->save($db);
	}
	
	function comment(PDO $db, string $name, string $mail, string $body, string $password, int $point, bool $saveThread = true): Comment
	{
		if ($this->id === null) throw new ApplicationException("Thread id not set");
		if ($this->entry === null) throw new ApplicationException("Thread entry not set");

		$comment = new Comment($db);
		$comment->entryID = $this->id;
		$comment->name = $name;
		$comment->mail = $mail;
		$comment->body = $body;
		$comment->hash = Util::hash($password);
		$comment->host = Util::getRemoteHost();
		
		if ($point)
		{
			$comment->evaluation = $eval = new Evaluation($db);
			$eval->id = &$comment->id;
			$eval->dateTime = &$comment->dateTime;
			$eval->entryID = &$comment->entryID;
			$eval->host = &$comment->host;
			$eval->point = $point;
			$this->evaluations[$eval->id] = $eval;
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
		if ($this->entry === null) throw new ApplicationException("Thread entry not set");

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
		if ($this->entry === null) throw new ApplicationException("Thread entry not set");

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
		$rt = self::query($db, sprintf
		('
			where %s.id = %d',
			App::THREAD_TABLE,
			$id
		));
		
		if ($rt)
		{
			$rt = $rt[0];
			$rt->entry = ThreadEntry::load($db, $id);
			$rt->loaded = true;
			$rt->updatePropertyLink();
			$rt->evaluations = Evaluation::getEvaluationsFromEntryID($db, $rt->id);
			$rt->comments = Comment::getCommentsFromEntryID($db, $rt->id, $rt->evaluations);
			$resEval = array_map(fn(Comment $_) => $_->evaluation?->id, $rt->comments);
			$rt->nonCommentEvaluations = array();
			
			foreach ($rt->evaluations as $i)
				if (!in_array($i->id, $resEval))
					$rt->nonCommentEvaluations[$i->id] = $i;
			
			return $rt;
		}
		else
			return null;
	}
	
	static function loadWithMegalith(PDO $db, PDO $idb, int $id): ?Thread
	{
		if (!($rt = Thread::load($db, $id)))
			if (Configuration::$instance->convertOnDemand &&
				is_dir("Megalith/sub") &&
				is_file($path = "Megalith/dat/{$id}.dat"))
			{
				$subject = 0;
				
				foreach (glob("Megalith/sub/subject*.txt") as $i)
					if (($n = basename($i)) != "subjects.txt" &&
						strpos(file_get_contents($i), "{$id}.dat") !== false)
						$subject = $n == "subject.txt"
							? Board::getLatestSubject($db)
							: intval(strtr($n, array
							(
								"subject" => "",
								".txt" => ""
							)));
				
				$db->beginTransaction();
				
				if ($db !== $idb)
					$idb->beginTransaction();
				
				$rt = Util::convertAndSaveToThread($db, $idb, $subject, $path, "Megalith/com/{$id}.res.dat", "Megalith/aft/{$id}.aft.dat");
				
				if ($db !== $idb)
					$idb->commit();
				
				$db->commit();
			}
		
		return $rt;
	}
	
	function save(PDO $db, bool $setSubjectLastUpdate = true): void
	{
		if ($this->entry === null) throw new ApplicationException("Thread entry not set");

		$this->entry->save($db, $setSubjectLastUpdate);
		Util::saveToTable($db, $this, self::$threadSchema, App::THREAD_TABLE);
		Util::saveToTable($db, $this, self::$threadStyleSchema, App::THREAD_STYLE_TABLE);
		Util::saveToTable($db, $this, self::$threadPasswordSchema, App::THREAD_PASSWORD_TABLE);
		$this->loaded = true;
	}
	
	static function ensureTable(PDO $db): void
	{
		$db->beginTransaction();
		
		if (Util::hasTable($db, App::THREAD_TABLE))
		{
			$currentThreadSchemaVersion = intval(Meta::get($db, App::THREAD_TABLE, "1"));
			
			if ($currentThreadSchemaVersion < 2)
				if (Configuration::$instance->dataStore instanceof SQLiteDataStore)
					Configuration::$instance->dataStore->alterTable($db, self::$threadSchema, App::THREAD_TABLE);
				else
					Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf('alter table %s drop primary key, add primary key(id)', App::THREAD_TABLE))));
		}
		
		if (Util::hasTable($db, App::THREAD_STYLE_TABLE))
		{
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
		
		$db->commit();
	}
	
	/**
	 * @return Thread[]
	 */
	private static function query(PDO $db, string $options): array
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select * from %s
			left join %s on %1$s.id = %2$s.id
			left join %s on %1$s.id = %3$s.id
			%s',
			App::THREAD_TABLE,
			App::THREAD_STYLE_TABLE,
			App::THREAD_PASSWORD_TABLE,
			trim($options)
		)));
		Util::executeStatement($st);
		
		if ($st === null) return array();

		return array_map(function (Thread $instance): Thread
		{
			$instance->convertLineBreak = (bool)$instance->convertLineBreak;
			return $instance;
		}, $st->fetchAll(PDO::FETCH_CLASS, "Thread"));
	}
	
	/**
	 * @return Thread[]
	 */
	static function getThreadsBySubject(PDO $db, int $subject, int $order = Board::ORDER_DESCEND): array
	{
		$entries = ThreadEntry::getEntriesBySubject($db, $subject, $order);
		
		foreach ($rt = self::query($db, sprintf
		('
			where %s.subject = %d
			order by %1$s.id %s',
			App::THREAD_TABLE,
			$subject,
			$order == Board::ORDER_ASCEND ? "asc" : "desc"
		)) as $i)
		{
			foreach ($entries as $j)
				if ($j->id === $i->id)
				{
					$i->entry = $j;
					
					break;
				}
			
			$i->loaded = true;
		}
		
		return $rt;
	}
}
?>
