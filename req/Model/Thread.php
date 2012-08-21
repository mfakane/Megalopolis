<?php
class Thread
{
	const REGEX_SPLIT_PAGE = "@<split\s*?/>@";
	
	static $threadSchema = array
	(
		"id" => "integer primary key not null",
		"subject" => "integer primary key not null",
		
		"body" => "text",
		"afterword" => "text"
	);
	static $threadStyleSchemaVersion = 2;
	static $threadStyleSchema = array
	(
		"id" => "integer primary key not null",

		"convertLineBreak" => "bit",
		"foreground" => "text",
		"background" => "text",
		"backgroundImage" => "text",
		"border" => "text"
	);
	static $threadPasswordSchema = array
	(
		"id" => "integer primary key not null",
	
		"hash" => "text"
	);
	
	/**
	 * @var ThreadEntry
	 */
	public $entry = null;
	
	public $id = 0;
	public $subject = 0;
	
	public $body = null;
	public $afterword = null;
	
	public $convertLineBreak = true;
	public $foreground = null;
	public $background = null;
	public $backgroundImage = null;
	public $border = null;
	
	public $hash = null;
	
	public $comments = array();
	public $evaluations = array();
	public $nonCommentEvaluations = array();
	
	public $loaded = false;
	
	function __construct(PDO $db = null)
	{
		$this->entry = new ThreadEntry($db);
		$this->updatePropertyLink();
	}
	
	/**
	 * @return array
	 */
	function toArray()
	{
		return array
		(
			"entry" => $this->entry->toArray(),
			"tags" => Configuration::$instance->showTags[Configuration::ON_ENTRY] ? $this->entry->tags : null,
			"body" => $this->body,
			"afterword" => $this->afterword,
			"convertLineBreak" => $this->convertLineBreak,
			"foreground" => $this->foreground,
			"background" => $this->background,
			"backgroundImage" => $this->backgroundImage,
			"border" => $this->border,
			"nonCommentEvaluation" => array_reduce($this->nonCommentEvaluations, create_function('$x, $y', 'return $x + $y->point;'), 0),
			"comments" => array_map(create_function('$_', 'return $_->toArray();'), $this->comments)
		);
	}
	
	function pageCount()
	{
		$matches = array();
		
		return preg_match_all(self::REGEX_SPLIT_PAGE, $this->body, $matches) + 1;
	}
	
	function page($page)
	{
		$rt = preg_split(self::REGEX_SPLIT_PAGE, $this->body);
		
		return $rt[$page - 1];
	}
	
	private function updatePropertyLink()
	{
		$this->id = &$this->entry->id;
		$this->subject = &$this->entry->subject;
	}
	
	/**
	 * @param int $point
	 * @param bool $saveThread [optional]
	 * @return Evaluation
	 */
	function evaluate(PDO $db, $point, $saveThread = true)
	{
		$eval = new Evaluation($db);
		$eval->entryID = $this->id;
		$eval->point = $point;
		$eval->host = $_SERVER["REMOTE_ADDR"];
		$eval->save($db);
		$this->nonCommentEvaluations[$eval->id] = $this->evaluations[$eval->id] = $eval;
		$this->entry->updateCount($this);
		
		if ($saveThread)
			$this->save($db);
		
		return $eval;
	}
	
	function unevaluate(PDO $db, Evaluation $eval)
	{
		unset($this->evaluations[$eval->id]);
		unset($this->nonCommentEvaluations[$eval->id]);
		
		$eval->delete($db);
		
		foreach ($this->comments as $i)
			if ($i->evaluation && $i->evaluation->id == $eval->id)
			{
				$i->evaluation = null;
				$i->save($db);
			}
		
		$this->entry->updateCount($this);
		$this->save($db);
	}
	
	/**
	 * @param string $name
	 * @param string $mail
	 * @param string $body
	 * @param string $password
	 * @param int $point
	 * @param bool $saveThread [optional]
	 * @return Comment
	 */
	function comment(PDO $db, $name, $mail, $body, $password, $point, $saveThread = true)
	{
		$comment = new Comment($db);
		$comment->entryID = $this->id;
		$comment->name = $name;
		$comment->mail = $mail;
		$comment->body = $body;
		$comment->hash = Util::hash($password);
		$comment->host = $_SERVER["REMOTE_ADDR"];
		
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
		$this->entry->updateCount($this);
		
		if ($saveThread)
			$this->save($db);
		
		return $comment;
	}
	
	function uncomment(PDO $db, Comment $comment)
	{
		unset($this->comments[$comment->id]);
		
		if ($comment->evaluation)
			unset($this->evaluations[$comment->evaluation->id]);
		
		$comment->delete($db);
		$this->entry->updateCount($this);
		$this->save($db);
	}
	
	function delete(PDO $db)
	{
		$this->entry->delete($db);
		$this->loaded = false;
	}
	
	/**
	 * @param int $id
	 * @return Comment
	 */
	function getCommentByID(PDO $db, $id)
	{
		$rt = array_filter($this->comments, create_function('$_', 'return $_->id == ' . $id . ';'));
		
		if ($rt)
			return array_pop($rt);
		else
			return null;
	}
	
	/**
	 * @param int $id
	 * @return Evaluation
	 */
	function getEvaluationByID(PDO $db, $id)
	{
		$rt = array_filter($this->nonCommentEvaluations, create_function('$_', 'return $_->id == ' . $id . ';'));
		
		if ($rt)
			return array_pop($rt);
		else
			return null;
	}
	
	/**
	 * @param int $id
	 * @return Thread
	 */
	static function load(PDO $db, $id)
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
			$rt->comments = Comment::getCommentsFromEntryID($db, $rt->id);
			$resEval = array_map(create_function('$_', 'return $_->evaluation ? $_->evaluation->id : 0;'), $rt->comments);
			$rt->evaluations = Evaluation::getEvaluationsFromEntryID($db, $rt->id);
			$rt->nonCommentEvaluations = array();
			
			foreach ($rt->evaluations as $i)
				if (!in_array($i->id, $resEval))
					$rt->nonCommentEvaluations[$i->id] = $i;
			
			return $rt;
		}
		else
			return null;
	}
	
	function save(PDO $db)
	{
		$this->entry->save($db);
		Util::saveToTable($db, $this, self::$threadSchema, App::THREAD_TABLE);
		Util::saveToTable($db, $this, self::$threadStyleSchema, App::THREAD_STYLE_TABLE);
		Util::saveToTable($db, $this, self::$threadPasswordSchema, App::THREAD_PASSWORD_TABLE);
		$this->loaded = true;
	}
	
	static function ensureTable(PDO $db)
	{
		Util::createTableIfNotExists($db, self::$threadSchema, App::THREAD_TABLE);
		Util::createTableIfNotExists($db, self::$threadStyleSchema, App::THREAD_STYLE_TABLE);
		Util::createTableIfNotExists($db, self::$threadPasswordSchema, App::THREAD_PASSWORD_TABLE);
		
		if (intval(Meta::get($db, App::THREAD_STYLE_TABLE, "1")) < self::$threadStyleSchemaVersion)
		{
			Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf('alter table %s add column border text', App::THREAD_STYLE_TABLE))));
			Meta::set($db, App::THREAD_STYLE_TABLE, strval(self::$threadStyleSchemaVersion));
		}
	}
	
	/**
	 * @param string $options [optional]
	 * @return array of Thread
	 */
	private static function query(PDO $db, $options)
	{
		return Util::ensureStatement($db, $db->query(sprintf
		('
			select * from %s
			left join %s on %1$s.id = %2$s.id
			left join %s on %1$s.id = %3$s.id
			%s',
			App::THREAD_TABLE,
			App::THREAD_STYLE_TABLE,
			App::THREAD_PASSWORD_TABLE,
			trim($options)
		)))->fetchAll(PDO::FETCH_CLASS, "Thread");
	}
	
	/**
	 * @param int $subject
	 * @param int $order [optional]
	 * @return array of Thread
	 */
	static function getThreadsBySubject(PDO $db, $subject, $order = Board::ORDER_DESCEND)
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
				if ($j->id == $i->id)
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