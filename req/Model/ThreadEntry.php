<?php
class ThreadEntry
{
	static $threadEntrySchemaVersion = 3;
	static $threadEntrySchema = array
	(
		"id" => "bigint primary key not null",
		"subject" => "integer not null",
		
		"title" => "varchar(255)",
		"name" => "varchar(255)",
		"summary" => "text",
		"link" => "varchar(512)",
		"mail" => "varchar(255)",
		"host" => "varchar(512)",
		"dateTime" => "bigint",
		"lastUpdate" => "bigint",
		"pageCount" => "integer",
		"size" => "real"
	);
	static $threadEvaluationSchemaVersion = 2;
	static $threadEvaluationSchema = array
	(
		"id" => "bigint primary key not null",

		"points" => "integer",
		"responseCount" => "integer",
		"commentCount" => "integer",
		"evaluationCount" => "integer",
		"readCount" => "integer"
	);
	static $threadTagSchema = array
	(
		"id" => "bigint primary key not null",

		"tag" => "varchar(255) primary key not null"
	);
	
	const SEARCH_RANDOM = 0;
	const SEARCH_ASCENDING = 1;
	const SEARCH_DESCENDING = 2;
	
	public $id = 0;
	public $subject = 0;
	public $title = null;
	public $name = null;
	public $summary = null;
	public $link = null;
	public $mail = null;
	public $host = null;
	public $dateTime = 0;
	public $lastUpdate = 0;
	public $pageCount = 0;
	public $size = 0.0;
	
	public $points = 0;
	public $rate = 0.0;
	public $responseCount = 0;
	public $commentCount = 0;
	public $evaluationCount = 0;
	public $readCount = 0;
	
	public $tags = array();
	
	public $loaded = false;
	
	function __construct(PDO $db = null)
	{
		if ($db)
		{
			$this->id = time();
			$this->dateTime = time();
			$s = Board::getLatestSubject($db);
			$this->subject = max($s, 1);
			
			while (true)
			{
				$st = Util::ensureStatement($db, $db->prepare(sprintf
				('
					select count(id) from %s
					where id = ?',
					App::THREAD_ENTRY_TABLE
				)));
				Util::executeStatement($st, array($this->id));
				
				if (array_pop($st->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0)) > 0)
					$this->id++;
				else
					break;
			}
			
			if ($s > 0 &&
				count(ThreadEntry::getEntriesBySubject($db, $s)) >= Configuration::$instance->subjectSplitting)
				Board::$latestSubject = ++$this->subject;
		}
	}
	
	/**
	 * @param int $id
	 * @return ThreadEntry
	 */
	static function load(PDO $db, $id)
	{	
		$rt = self::query($db, sprintf
		('
			where %s.id = ?',
			App::THREAD_ENTRY_TABLE
		), array($id));
		
		if ($rt)
		{
			$rt = array_pop($rt);
			$tags = self::queryTags($db, 'where id = ?', array($id));
			
			if (isset($tags[$rt->id]))
				$rt->tags = $tags[$rt->id];
			
			$rt->calculateRate();
			$rt->loaded = true;
		
			return $rt;
		}
		else
			return null;
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
			"subject" => intval($this->subject),
			"title" => $this->title,
			"name" => $c->showName[Configuration::ON_ENTRY] ? $this->name : null,
			"summary" => $c->useSummary && $c->showSummary[Configuration::ON_ENTRY] ? $this->summary : null,
			"link" => $c->showName[Configuration::ON_ENTRY] ? $this->link : null,
			"mail" => $c->showName[Configuration::ON_ENTRY] ? $this->mail : null,
			"dateTime" => intval($this->dateTime),
			"lastUpdate" => intval($this->lastUpdate),
			"pageCount" => $c->showPages[Configuration::ON_ENTRY] ? intval($this->pageCount) : null,
			"size" => $c->showSize[Configuration::ON_ENTRY] ? intval($this->size) : null,
			"points" => $c->showPoint[Configuration::ON_ENTRY] ? intval($this->points) : null,
			"responseCount" => $c->showComment[Configuration::ON_ENTRY] ? intval($this->responseCount) : null,
			"commentCount" => $c->showComment[Configuration::ON_ENTRY] ? intval($this->commentCount) : null,
			"evaluationCount" => $c->showComment[Configuration::ON_ENTRY] ? intval($this->evaluationCount) : null,
			"readCount" => $c->showReadCount[Configuration::ON_ENTRY] ? intval($this->readCount) : null
		);
	}
	
	function incrementReadCount(PDO $db)
	{
		Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf
		('
			update %s
			set readCount = ?
			where id = ?',
			App::THREAD_EVALUATION_TABLE
		))), array(++$this->readCount, $this->id));
	}
	
	function updateCount(Thread $thread)
	{
		$this->responseCount = count($thread->nonCommentEvaluations) + count($thread->comments);
		$this->evaluationCount = count($thread->evaluations);
		$this->commentCount = count($thread->comments);
		$this->points = array_reduce($thread->evaluations, create_function('$x, $y', 'return $x + $y->point;'), 0);
		$this->calculateRate();
	}
	
	private function calculateRate()
	{
		switch (Configuration::$instance->rateType)
		{
			case Configuration::RATE_FIVE:
				$this->rate = round(($this->points + 25) / (($this->evaluationCount + 1) * 50) * 10, 2);
				
				break;
			case Configuration::RATE_AVERAGE:
				$this->rate = $this->evaluationCount == 0 ? 0 : round($this->points / $this->evaluationCount, 2);
				
				break;
		}
	}
	
	function delete(PDO $db, PDO $idb)
	{
		self::deleteDirect($db, $idb, $this->id);
		
		$this->loaded = false;
	}
	
	function save(PDO $db)
	{
		Util::saveToTable($db, $this, self::$threadEntrySchema, App::THREAD_ENTRY_TABLE);
		Util::saveToTable($db, $this, self::$threadEvaluationSchema, App::THREAD_EVALUATION_TABLE);
		
		Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf
		('
			delete from %s
			where id = %d',
			App::THREAD_TAG_TABLE,
			$this->id
		))));
		
		foreach ($this->tags as $i)
		{
			$st = Util::ensureStatement($db, $db->prepare(sprintf
			('
				insert into %s
				(
					%s
				)
				values
				(
					:%s
				)',
				App::THREAD_TAG_TABLE,
				implode(", ", array_keys(self::$threadTagSchema)),
				implode(", :", array_keys(self::$threadTagSchema))
			)));
			Util::bindValues($st, $this, self::$threadTagSchema);
			$st->bindParam("tag", $i);
			Util::executeStatement($st);
		}
		
		$this->loaded = true;
	}
	
	static function ensureTable(PDO $db)
	{
		$db->beginTransaction();
		
		$threadEntryIndices = array
		(
			App::THREAD_ENTRY_TABLE . "SubjectIndex" => array("subject"),
			App::THREAD_ENTRY_TABLE . "NameIndex" => array("name")
		);
		
		if (Util::hasTable($db, App::THREAD_ENTRY_TABLE))
		{
			$currentThreadEntrySchemaVersion = intval(Meta::get($db, App::THREAD_ENTRY_TABLE, "1"));
			
			if ($currentThreadEntrySchemaVersion < 2)
				if (Configuration::$instance->dataStore instanceof SQLiteDataStore)
					Configuration::$instance->dataStore->alterTable($db, self::$threadEntrySchema, App::THREAD_ENTRY_TABLE, $threadEntryIndices);
				else
					Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf('alter table %s drop primary key, add primary key(id)', App::THREAD_ENTRY_TABLE))));
			
			if ($currentThreadEntrySchemaVersion < 3)
				Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf('create index %s on %s(dateTime)', App::THREAD_ENTRY_TABLE . "DateTimeIndex", App::THREAD_ENTRY_TABLE))));
		}
		
		if (Util::hasTable($db, App::THREAD_EVALUATION_TABLE))
		{
			$currentThreadEvaluationSchemaVersion = intval(Meta::get($db, App::THREAD_EVALUATION_TABLE, "1"));
			
			if ($currentThreadEvaluationSchemaVersion < 2)
			{
				Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf('create index %s on %s(evaluationCount)', App::THREAD_EVALUATION_TABLE . "EvaluationCountIndex", App::THREAD_EVALUATION_TABLE))));
				Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf('create index %s on %s(points)', App::THREAD_EVALUATION_TABLE . "PointsIndex", App::THREAD_EVALUATION_TABLE))));
			}
		}
		
		Util::createTableIfNotExists($db, self::$threadEntrySchema, App::THREAD_ENTRY_TABLE, $threadEntryIndices);
		Util::createTableIfNotExists($db, self::$threadEvaluationSchema, App::THREAD_EVALUATION_TABLE);
		Util::createTableIfNotExists($db, self::$threadTagSchema, App::THREAD_TAG_TABLE, array
		(
			App::THREAD_TAG_TABLE . "TagIndex" => array("tag")
		));
		Meta::set($db, App::THREAD_ENTRY_TABLE, strval(self::$threadEntrySchemaVersion));
		Meta::set($db, App::THREAD_EVALUATION_TABLE, strval(self::$threadEvaluationSchemaVersion));
		
		$db->commit();
	}
	
	/**
	 * @param string $options [optional]
	 * @return mixed
	 */
	private static function query(PDO $db, $options = "", array $params = array(), array $columns = array("*"))
	{
		static $queryCache = array();
		
		$rt = array();
		$sql = sprintf
		('
			select %s from %s
			left join %s on %2$s.id = %3$s.id
			%s',
			implode(", ", $columns),
			App::THREAD_ENTRY_TABLE,
			App::THREAD_EVALUATION_TABLE,
			trim($options)
		);
		$st = isset($queryCache[$sql]) ? $queryCache[$sql] : $queryCache[$sql] = Util::ensureStatement($db, $db->prepare($sql));
		Util::executeStatement($st, $params);
		
		if ($columns == array("*"))
		{
			foreach ($st->fetchAll(PDO::FETCH_CLASS, "ThreadEntry") as $i)
				$rt[$i->id] = $i;
			
			return $rt;
		}
		else
			return $st->fetchAll();
	}
	
	/**
	 * @param string $options [optional]
	 * @return array of (array of string) by string
	 */
	private static function queryTags(PDO $db, $options = "", array $params = array())
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select id, tag from %s
			%s',
			App::THREAD_TAG_TABLE,
			trim($options)
		)));
		$st->execute($params);
		
		return $st->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_GROUP);
	}
	
	private static function getAllMegalithEntryIDs($latest)
	{
		$rt = array();
		
		foreach (glob("Megalith/sub/subject*.txt") as $i)
			if (($n = basename($i)) != "subjects.txt")
			{
				$subject = $n == "subject.txt"
					? $latest
					: intval(strtr($n, array
					(
						"subject" => "",
						".txt" => ""
					)));
				
				foreach (array_map(create_function('$_', 'return mb_convert_encoding($_, "UTF-8", "SJIS");'), file($i, FILE_IGNORE_NEW_LINES)) as $j)
					if (strstr($id, "<>"))
						$rt[] = intval(array_shift(explode("<>", $j)));
			}
		
		return $rt;
	}
	
	private static function getAllMegalithEntries($latest, $getSize = false)
	{
		$rt = array();
		
		foreach (glob("Megalith/sub/subject*.txt") as $i)
			if (($n = basename($i)) != "subjects.txt")
			{
				$subject = $n == "subject.txt"
					? $latest
					: intval(strtr($n, array
					(
						"subject" => "",
						".txt" => ""
					)));
				
				foreach (array_map(create_function('$_', 'return mb_convert_encoding($_, "UTF-8", "SJIS");'), file($i, FILE_IGNORE_NEW_LINES)) as $j)
				{
					$entry = Util::convertLineToThreadEntry($j);
					
					if (!$entry)
						continue;
					
					$entry->subject = $subject;
					$entry->size = $getSize && is_file($file = "Megalith/dat/{$entry->id}.dat") ? round(filesize($file) / 1024, 2) : 0;
					
					$rt[] = $entry;
				}
			}
		
		return $rt;
	}
	
	private static function searchAllMegalithEntries(PDO $db, array $query)
	{
		$rt = array();
		
		foreach (self::getAllMegalithEntries(Board::getLatestSubject($db)) as $i)
		{
			$matches = true;
			
			if ($matches && isset($query["title"]) && $query["title"])
				foreach ($query["title"] as $j)
					$matches = $matches && mb_strpos($i->title, $j) !== false;
			
			if ($matches && isset($query["name"]) && $query["name"] && Configuration::$instance->showName[Configuration::ON_SUBJECT])
				foreach ($query["name"] as $j)
					$matches = $matches && mb_strpos($i->name, $j) !== false;
			
			if ($matches && isset($query["tag"]) && $query["tag"] && Configuration::$instance->showTags[Configuration::ON_SUBJECT])
			{
				$tags = implode(" ", $i->tags);
				
				foreach ($query["tag"] as $j)
					$matches = $matches && mb_strpos($tags, $j) !== false;
			}
			
			if ($matches && isset($query["eval"]) && $query["eval"])
				$matches = $matches && $i->evaluationCount >= $query["eval"][0] && $i->evaluationCount <= $query["eval"][1];
			
			if ($matches && isset($query["points"]) && $query["points"])
				$matches = $matches && $i->points >= $query["points"][0] && $i->points <= $query["points"][1];
			
			if ($matches && isset($query["dateTime"]) && $query["dateTime"])
				$matches = $matches && $i->dateTime >= $query["dateTime"][0] && $i->dateTime <= $query["dateTime"][1];
			
			$body = $matches && is_file($aft = "Megalith/dat/{$i->id}.dat") ? mb_convert_encoding(implode("\r\n", file($aft, FILE_IGNORE_NEW_LINES)), "UTF-8", "SJIS") : "";
			$afterword = $matches && is_file($aft = "Megalith/aft/{$i->id}.aft.dat") ? mb_convert_encoding(implode("\r\n", file($aft, FILE_IGNORE_NEW_LINES)), "UTF-8", "SJIS") : "";
			
			if ($matches && isset($query["body"]) && $query["body"])
				foreach ($query["body"] as $j)
					$matches = $matches && mb_strpos($body, $j) !== false;
			
			if ($matches && isset($query["afterword"]) && $query["afterword"])
				foreach ($query["afterword"] as $j)
					$matches = $matches && mb_strpos($afterword, $j) !== false;
			
			if ($matches && isset($query["query"]) && $query["query"])
				foreach ($query["query"] as $j)
					$matches = $matches &&
					(
						mb_strpos($i->title, $j) !== false ||
						mb_strpos($i->name, $j) !== false ||
						mb_strpos($body, $j) !== false ||
						mb_strpos($afterword, $j) !== false
					);
			
			if ($matches)
				$rt[$i->id] = $i;
		}

		return $rt;
	}

	function getMegalithEntryIDsBySubject(PDO $db, $subject)
	{
		$rt = array();
		
		if (is_file($path = "Megalith/sub/" . ($subject == Board::getLatestSubject($db) ? "subject.txt" : "subject{$subject}.txt")))
			foreach (array_reverse(file($path, FILE_IGNORE_NEW_LINES)) as $i)
				if (count($line = explode("<>", $i)) > 2)
					$rt[] = intval(str_replace(".dat", "", $line[0]));
		
		return $rt;
	}
	
	private function getMegalithEntriesBySubject(PDO $db, $subject)
	{
		$rt = array();
		
		if (is_file($path = "Megalith/sub/" . ($subject == Board::getLatestSubject($db) ? "subject.txt" : "subject{$subject}.txt")))
			foreach (array_reverse(file($path, FILE_IGNORE_NEW_LINES)) as $i)
			{
				$entry = Util::convertLineToThreadEntry(mb_convert_encoding($i, "UTF-8", "SJIS"));
				
				if (!$entry ||
					isset($rt[$entry->id]))
					continue;
				
				$entry->subject = $subject;
				$entry->size = is_file($file = "Megalith/dat/{$entry->id}.dat") ? round(filesize($file) / 1024, 2) : 0;
				$rt[$entry->id] = $entry;
			}
		
		return $rt;
	}
	
	/**
	 * @param int $subject
	 * @param int $order [optional]
	 * @return array of ThreadEntry
	 */
	static function getEntriesBySubject(PDO $db, $subject, $order = Board::ORDER_DESCEND)
	{
		$rt = self::query($db, sprintf
		('
			where %s.subject = %d
			group by %1$s.id',
			App::THREAD_ENTRY_TABLE,
			$subject
		));
		$tags = self::queryTags($db, sprintf
		('
			where id in (%s)',
			implode(", ", array_map(create_function('$_', 'return $_->id;'), $rt))
		));
		
		if (Configuration::$instance->convertOnDemand)
			$rt += self::getMegalithEntriesBySubject($db, $subject);
		
		foreach ($rt as $i)
		{
			if (!$i->tags && isset($tags[$i->id]))
				$i->tags = $tags[$i->id];
			
			$i->calculateRate();
			$i->loaded = true;
		}
		
		if ($order == Board::ORDER_DESCEND)
			krsort($rt);
		else
			ksort($rt);

		return $rt;
	}

	/**
	 * @param int $subject
	 * @return array|int
	 */
	static function getEntryIDsBySubject(PDO $db, $subject)
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select id from %s
			where subject = ?',
			App::THREAD_ENTRY_TABLE
		)));
		Util::executeStatement($st, array($subject));
		
		return array_map("intval", $st->fetchAll(PDO::FETCH_COLUMN, 0));
	}
	
	/**
	 * @param string $name
	 * @param int $order [optional]
	 * @return array of ThreadEntry
	 */
	static function getEntriesByName(PDO $db, $name, $order = Board::ORDER_DESCEND)
	{
		$rt = self::query($db, sprintf
		('
			where %s.name = ?
			order by %1$s.id %s',
			App::THREAD_ENTRY_TABLE,
			$order == Board::ORDER_ASCEND ? "asc" : "desc"
		), array($name));
		$tags = self::queryTags($db, sprintf
		('
			where id in (%s)',
			implode(", ", array_map(create_function('$_', 'return $_->id;'), $rt))
		));
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
		{
			foreach (self::getAllMegalithEntries(Board::getLatestSubject($db), true) as $i)
				if (!isset($rt[$i->id]) && $i->name == $name)
					$rt[$i->id] = $i;
			
			krsort($rt);
		}
		
		foreach ($rt as $i)
		{
			if (!$i->tags && isset($tags[$i->id]))
				$i->tags = $tags[$i->id];
			
			$i->calculateRate();
			$i->loaded = true;
		}
		
		return $rt;
	}
	
	/**
	 * @param string $tag
	 * @param int $offset
	 * @param int $limit
	 * @param int $order [optional]
	 * @return array of ThreadEntry
	 */
	static function getEntriesByTag(PDO $db, $tag, $offset = 0, $limit = null, $order = Board::ORDER_DESCEND)
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select id from %s
			where tag = ?',
			App::THREAD_TAG_TABLE
		)));
		Util::executeStatement($st, array($tag));
		$ids = implode(", ", $st->fetchAll(PDO::FETCH_COLUMN));
		
		$rt = self::query($db, sprintf
		('
			where %s.id in (%s)
			order by %1$s.id %s
			%s',
			App::THREAD_ENTRY_TABLE,
			$ids,
			$order == Board::ORDER_ASCEND ? "asc" : "desc",
			is_null($limit) ? "" : "limit {$limit} offset {$offset}"
		));
		$tags = self::queryTags($db, sprintf
		('
			where id in (%s)',
			$ids
		));
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
		{
			foreach (self::getAllMegalithEntries(Board::getLatestSubject($db)) as $i)
				if (!isset($rt[$i->id]) && in_array($tag, $i->tags))
					$rt[$i->id] = $i;
			
			krsort($rt);
		}
		
		foreach ($rt as $i)
		{
			if (isset($tags[$i->id]))
				$i->tags = $tags[$i->id];
			
			$i->calculateRate();
			$i->loaded = true;
		}
		
		return $rt;
	}
	
	/**
	 * @return int
	 */
	static function getNameCount(PDO $db)
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select count(distinct name) from %s',
			App::THREAD_ENTRY_TABLE
		)));
		Util::executeStatement($st);
		$rt = $st->fetchAll(PDO::FETCH_COLUMN);
		
		return $rt ? $rt[0] : 0;
	}
	
	/**
	 * @return int
	 */
	static function getTagCount(PDO $db)
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select count(distinct tag) from %s',
			App::THREAD_TAG_TABLE
		)));
		Util::executeStatement($st);
		$rt = $st->fetchAll(PDO::FETCH_COLUMN);
		
		return $rt ? $rt[0] : 0;
	}

	/**
	 * @param int $offset
	 * @param int $limit
	 * @param int $order [optional]
	 * @return array
	 */
	static function getNames(PDO $db, $offset = 0, $limit = null, $order = Board::ORDER_DESCEND)
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select name, count(id) from %s
			where name != ""
			group by name
			order by count(id) %s
			%s',
			App::THREAD_ENTRY_TABLE,
			$order == Board::ORDER_ASCEND ? "asc" : "desc",
			is_null($limit) ? "" : "limit {$limit} offset {$offset}"
		)));
		Util::executeStatement($st);
		$rt = $st->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_GROUP);
		$rt = array_map(create_function('$_', 'return $_[0];'), $rt);
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
		{
			foreach (self::getAllMegalithEntries(0) as $i)
				if (isset($rt[$i->name]))
					$rt[$i->name]++;
				else
					$rt[$i->name] = 1;
			
			uasort($rt, create_function('$x, $y', 'return $y - $x;'));
		}
		
		return $rt;
	}

	/**
	 * @param int $offset
	 * @param int $limit
	 * @param int $order [optional]
	 * @return array
	 */
	static function getTags(PDO $db, $offset = 0, $limit = null, $order = Board::ORDER_DESCEND)
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select tag, count(id) from %s
			group by tag
			order by count(id) %s
			%s',
			App::THREAD_TAG_TABLE,
			$order == Board::ORDER_ASCEND ? "asc" : "desc",
			is_null($limit) ? "" : "limit {$limit} offset {$offset}"
		)));
		Util::executeStatement($st);
		$rt = $st->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_GROUP);
		$rt = array_map(create_function('$_', 'return $_[0];'), $rt);
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
		{
			foreach (self::getAllMegalithEntries(0) as $i)
				foreach ($i->tags as $j)
					if (isset($rt[$j]))
						$rt[$j]++;
					else
						$rt[$j] = 1;
			
			uasort($rt, create_function('$x, $y', 'return $y - $x;'));
		}
		
		return $rt;
	}
	
	/**
	 * @param string $name
	 * @return int
	 */
	static function getEntryCountByName(PDO $db, $name)
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select count(1) from %s
			where name = ?',
			App::THREAD_ENTRY_TABLE
		)));
		Util::executeStatement($st, array($name));
		$rt = $st->fetchAll(PDO::FETCH_COLUMN);
		$rt = $rt[0];
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
			foreach (self::getAllMegalithEntries(0) as $i)
				if ($i->name == $name)
					$rt++;
		
		return $rt;
	}
	
	/**
	 * @param string $tag
	 * @return int
	 */
	static function getEntryCountByTag(PDO $db, $tag)
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select count(1) from %s
			where tag = ?',
			App::THREAD_TAG_TABLE
		)));
		Util::executeStatement($st, array($tag));
		$rt = $st->fetchAll(PDO::FETCH_COLUMN);
		$rt = $rt[0];
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
			foreach (self::getAllMegalithEntries(0) as $i)
				if (in_array($tag, $i->tags))
					$rt++;
		
		return $rt;
	}
	
	/**
	 * @return ThreadEntry
	 */
	static function getRandomEntry(PDO $db)
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select id from %s',
			App::THREAD_ENTRY_TABLE
		)));
		Util::executeStatement($st);
		$rt = $st->fetchAll(PDO::FETCH_COLUMN);
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
			$rt = array_unique(array_merge($rt, self::getAllMegalithEntryIDs(0)));
		
		if ($rt)
			return self::load($db, $rt[array_rand($rt)]);
		else
			return null;
	}
	
	static function getMaxMinValues(PDO $db)
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select
				max(a.evaluationCount) as maxEval,
				min(a.evaluationCount) as minEval,
				max(b.points) as maxPoints,
				min(b.points) as minPoints,
				max(c.dateTime) as maxDateTime,
				min(c.dateTime) as minDateTime
				from %2$s as a, %2$s as b, %1$s as c',
			App::THREAD_ENTRY_TABLE,
			App::THREAD_EVALUATION_TABLE
		)));
		Util::executeStatement($st);
		
		$rt = $st->fetch();
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
			foreach (self::getAllMegalithEntries(0) as $i)
				$rt = array
				(
					"maxDateTime" => max($rt["maxDateTime"], $i->dateTime),
					"minDateTime" => min($rt["minDateTime"], $i->dateTime),
					"maxEval" => max($rt["maxEval"], $i->evaluationCount),
					"minEval" => min($rt["minEval"], $i->evaluationCount),
					"maxPoints" => max($rt["maxPoints"], $i->points),
					"minPoints" => min($rt["minPoints"], $i->points),
				);
		
		return $rt;
	}
	
	/**
	 * @param int $offset [optional]
	 * @param int $limit [optional]
	 * @param int $option [optional]
	 * @param string $sort [optional]
	 * @return array|ThreadEntry
	 */
	static function search(PDO $db, PDO $idb, array $query, $offset = 0, $limit = null, $option = self::SEARCH_DESCENDING, $sort = "1")
	{
		$ids = null;
		
		if (isset($query["query"]) && $query["query"])
			$ids = SearchIndex::search($idb, $query["query"], array_filter(array
			(
				"title",
				Configuration::$instance->showName[Configuration::ON_SUBJECT] ? "name" : null,
				Configuration::$instance->useSummary && Configuration::$instance->showSummary[Configuration::ON_SUBJECT] ? "summary" : null,
				"body",
				"afterword",
				Configuration::$instance->showTags[Configuration::ON_SUBJECT] ? "tag" : null
			)));
		
		if (isset($query["title"]) && $query["title"])
			$ids = SearchIndex::search($idb, $query["title"], array("title"), $ids);
		
		if (isset($query["name"]) && $query["name"] && Configuration::$instance->showName[Configuration::ON_SUBJECT])
			$ids = SearchIndex::search($idb, $query["name"], array("name"), $ids);
		
		if (isset($query["summary"]) && $query["summary"] && Configuration::$instance->useSummary && Configuration::$instance->showSummary[Configuration::ON_SUBJECT])
			$ids = SearchIndex::search($idb, $query["summary"], array("summary"), $ids);
		
		if (isset($query["body"]) && $query["body"])
			$ids = SearchIndex::search($idb, $query["body"], array("body"), $ids);
		
		if (isset($query["afterword"]) && $query["afterword"])
			$ids = SearchIndex::search($idb, $query["afterword"], array("afterword"), $ids);
		
		if (isset($query["tag"]) && $query["tag"] && Configuration::$instance->showTags[Configuration::ON_SUBJECT])
			$ids = SearchIndex::search($idb, $query["tag"], array("tag"), $ids);
		
		$where = array
		(
			!is_null($ids) ? App::THREAD_ENTRY_TABLE . ".id in (" . ($ids ? implode(", ", $ids) : -1) . ")" : null,
			isset($query["eval"]) && $query["eval"] ? "evaluationCount between {$query['eval'][0]} and {$query['eval'][1]}" : null,
			isset($query["points"]) && $query["points"] ? "points between {$query['points'][0]} and {$query['points'][1]}" : null,
			isset($query["dateTime"]) && $query["dateTime"] ? "dateTime between {$query['dateTime'][0]} and {$query['dateTime'][1]}" : null
		);
		$whereString = "where " . implode(" and ", array_filter($where));
		Util::executeStatement($st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select count(1) from %s
			left join %s on %1$s.id = %2$s.id
			%s',
			App::THREAD_ENTRY_TABLE,
			App::THREAD_EVALUATION_TABLE,
			$whereString
		))));
		$count = $st->fetch();
		
		if ($option == self::SEARCH_RANDOM)
		{
			$rt = self::query($db, $whereString, array(), array(App::THREAD_ENTRY_TABLE . ".id"));
			
			if (Configuration::$instance->convertOnDemand &&
				is_dir("Megalith/sub"))
				$rt = array_merge($rt, self::searchAllMegalithEntries($db, $query));
			
			$val = $rt[array_rand($rt)];
			
			return is_array($val) ? ThreadEntry::load($db, $val[0]) : $val;
		}
		else
		{
			$rt = self::query($db, "{$whereString} order by {$sort} " . ($option == self::SEARCH_DESCENDING ? "desc" : "asc") . ($limit ? " limit {$limit} offset {$offset}" : null));
			
			if ($rt)
			{
				$tags = self::queryTags($db, sprintf
				('
					where id in (%s)',
					implode(", ", array_map(create_function('$_', 'return $_->id;'), $rt))
				));
				
				foreach ($rt as $i)
				{
					if (!$i->tags && isset($tags[$i->id]))
						$i->tags = $tags[$i->id];
					
					$i->calculateRate();
					$i->loaded = true;
				}
			}
			
			if (Configuration::$instance->convertOnDemand &&
				is_dir("Megalith/sub"))
			{
				$entries = self::searchAllMegalithEntries($db, $query);
				$c = count(array_diff_key($entries, $rt));
				
				foreach (array_slice($entries, $offset + $count[0]) as $i)
					if (isset($rt[$i->id]))
						$c--;
					else if (!$limit || count($rt) < $limit)
						$rt[$i->id] = $i;
				
				$count[0] += $c;
			}
			
			return array
			(
				"result" => $rt,
				"count" => $count[0]
			);
		}
	}
	
	
	/**
	 * @param int $id
	 */
	static function deleteDirect(PDO $db, PDO $idb, $id)
	{
		foreach (array
		(
			App::THREAD_ENTRY_TABLE,
			App::THREAD_EVALUATION_TABLE,
			App::THREAD_PASSWORD_TABLE,
			App::THREAD_STYLE_TABLE,
			App::THREAD_TABLE,
			App::THREAD_TAG_TABLE,
		) as $i)
			Util::executeStatement(Util::ensureStatement($db, $db->prepare("delete from {$i} where id = ?")), array($id));
		
		foreach (array
		(
			App::EVALUATION_TABLE,
			App::COMMENT_TABLE,
		) as $i)
			Util::executeStatement(Util::ensureStatement($db, $db->prepare("delete from {$i} where entryID = ?")), array($id));
		
		if ($idb != null)
			SearchIndex::$instance->unregister($idb, $id);
	}
}
?>