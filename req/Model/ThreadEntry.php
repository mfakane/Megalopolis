<?php
namespace Megalopolis;

use \PDO;

class ThreadEntry
{
	static int $threadEntrySchemaVersion = 3;

	static array $threadEntrySchema = array
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
	static int $threadEvaluationSchemaVersion = 4;
	static array $threadEvaluationSchema = array
	(
		"id" => "bigint primary key not null",

		"points" => "integer",
		"responseCount" => "integer",
		"commentCount" => "integer",
		"evaluationCount" => "integer",
		"readCount" => "integer",
		"responseLastUpdate" => "bigint"
	);
	static int $threadTagSchemaVersion = 2;
	static array $threadTagSchema = array
	(
		"id" => "bigint primary key not null",

		"tag" => "varchar(255) primary key not null",
		"position" => "tinyint"
	);
	static int $authorSchemaVersion = 2;
	static array $authorSchema = array
	(
		"name" => "varchar(255) primary key not null",
		
		"threadCount" => "integer",
	);
	static int $tagSchemaVersion = 2;
	static array $tagSchema = array
	(
		"tag" => "varchar(255) primary key not null",
		
		"threadCount" => "integer",
	);
	
	const SEARCH_RANDOM = 0;
	const SEARCH_ASCENDING = 1;
	const SEARCH_DESCENDING = 2;
	
	public int $id = 0;
	public int $subject = 0;
	public ?string $title = null;
	public ?string $name = null;
	public ?string $summary = null;
	public ?string $link = null;
	public ?string $mail = null;
	public ?string $host = null;
	public int $dateTime = 0;
	public int $lastUpdate = 0;
	public int $pageCount = 0;
	public float $size = 0.0;
	
	public int $points = 0;
	public float $rate = 0.0;
	public int $responseCount = 0;
	public int $commentCount = 0;
	public int $evaluationCount = 0;
	public int $readCount = 0;
	public ?int $commentedEvaluationCount = null;
	public ?int $responseLastUpdate = null;
	
	/**
	 * @var string[]
	 */
	public array $tags = array();
	
	public bool $loaded = false;
	
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
				if (!$st) break;

				$rt = $st->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
				
				if (array_pop($rt) > 0)
					$this->id++;
				else
					break;
			}
			
			if ($s > 0 &&
				count(ThreadEntry::getEntriesBySubject($db, $s)) >= Configuration::$instance->subjectSplitting)
				Board::$latestSubject = ++$this->subject;
		}
	}
	
	function getLatestLastUpdate(): int
	{
		return max($this->lastUpdate, $this->responseLastUpdate ?? 0);
	}
	
	static function load(PDO $db, int $id): ?ThreadEntry
	{	
		$rt = self::query($db, sprintf
		('
			where %s.id = ?',
			App::THREAD_ENTRY_TABLE
		), array($id));
		
		if ($rt)
		{
			$rt = array_pop($rt);
			self::processResultEntries($db, array($rt));
		
			return $rt;
		}
		else
			return null;
	}

	/**
	 * @param Configuration::ON_* $on
	 */
	function toArray($on = Configuration::ON_ENTRY): array
	{
		$c = &Configuration::$instance;
		
		return array
		(
			"id" => intval($this->id),
			"subject" => intval($this->subject),
			"title" => $c->showTitle[Configuration::ON_SUBJECT] ? $this->title : null,
			"name" => $c->showName[$on] ? $this->name : null,
			"summary" => $c->useSummary && $c->showSummary[$on] ? $this->summary : null,
			"link" => $c->showName[$on] ? $this->link : null,
			"mail" => $c->showName[$on] ? $this->mail : null,
			"dateTime" => intval($this->dateTime),
			"lastUpdate" => $this->responseLastUpdate,
			"pageCount" => $c->showPages[$on] ? intval($this->pageCount) : null,
			"size" => $c->showSize[$on] ? floatval($this->size) : null,
			"points" => $c->showPoint[$on] ? intval($this->points) : null,
			"responseCount" => $c->showComment[$on] ? intval($this->responseCount) : null,
			"commentCount" => $c->showComment[$on] ? intval($this->commentCount) : null,
			"evaluationCount" => $c->showPoint[$on] ? intval($this->evaluationCount) : null,
			"readCount" => $c->showReadCount[$on] ? intval($this->readCount) : null,
			"tags" => $c->showTags[$on] ? $this->tags : null,
		);
	}
	
	function incrementReadCount(PDO $db): void
	{
		Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf
		('
			update %s
			set readCount = ?
			where id = ?',
			App::THREAD_EVALUATION_TABLE
		))), array(++$this->readCount, $this->id));
	}
	
	function updateCount(Thread $thread): void
	{
		$nonCommentEvaluationCount = count($thread->nonCommentEvaluations);
		
		$this->evaluationCount = count($thread->evaluations);
		$this->commentCount = count($thread->comments);
		$this->responseCount = $nonCommentEvaluationCount + $this->commentCount;
		$this->commentedEvaluationCount = $this->evaluationCount - $nonCommentEvaluationCount;
		$this->points = array_reduce($thread->evaluations, fn(int $x, Evaluation $y): int => $x + $y->point, 0);
		$this->calculateRate();
	}
	
	private function calculateRate(): void
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
	
	function delete(PDO $db, PDO $idb): void
	{
		self::deleteDirect($db, $idb, array($this->id));
		Board::setLastUpdate($db, $this->subject);
		
		$this->loaded = false;
	}
	
	function save(PDO $db, bool $setSubjectLastUpdate = true): void
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
		
		foreach ($this->tags as $k => $v)
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
			if ($st === null) continue;

			Util::bindValues($st, $this, self::$threadTagSchema);
			$st->bindParam("tag", $v);
			$st->bindParam("position", $k);
			Util::executeStatement($st);
		}
		
		if ($setSubjectLastUpdate)
			Board::setLastUpdate($db, $this->subject);
		
		$this->loaded = true;
	}
	
	static function ensureTable(PDO $db): void
	{
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
				Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf('create index %s on %s(evaluationCount)', App::THREAD_EVALUATION_TABLE . "EvaluationCountIndex", App::THREAD_EVALUATION_TABLE))), array(), false);
				Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf('create index %s on %s(points)', App::THREAD_EVALUATION_TABLE . "PointsIndex", App::THREAD_EVALUATION_TABLE))), array(), false);
			}
			
			if ($currentThreadEvaluationSchemaVersion < 3)
				Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf('alter table %s add column responseLastUpdate bigint', App::THREAD_EVALUATION_TABLE))), array(), false);
			
			if ($currentThreadEvaluationSchemaVersion < 4)
				if (Configuration::$instance->dataStore instanceof SQLiteDataStore)
					Configuration::$instance->dataStore->alterTable($db, self::$threadEvaluationSchema, App::THREAD_EVALUATION_TABLE);
				else
					Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf('alter table %s modify column responseLastUpdate bigint', App::THREAD_EVALUATION_TABLE))), array(), false);
		}
		
		if (Util::hasTable($db, App::THREAD_TAG_TABLE))
		{
			$currentThreadTagSchemaVersion = intval(Meta::get($db, App::THREAD_TAG_TABLE, "1"));
			
			if ($currentThreadTagSchemaVersion < 2)
				Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf('alter table %s add column position tinyint', App::THREAD_TAG_TABLE))), array(), false);
		}
		
		Util::createTableIfNotExists($db, self::$threadEntrySchema, App::THREAD_ENTRY_TABLE, $threadEntryIndices);
		Util::createTableIfNotExists($db, self::$threadEvaluationSchema, App::THREAD_EVALUATION_TABLE);
		Util::createTableIfNotExists($db, self::$threadTagSchema, App::THREAD_TAG_TABLE, array
		(
			App::THREAD_TAG_TABLE . "TagIndex" => array("tag")
		));
		Meta::set($db, App::THREAD_ENTRY_TABLE, strval(self::$threadEntrySchemaVersion));
		Meta::set($db, App::THREAD_EVALUATION_TABLE, strval(self::$threadEvaluationSchemaVersion));
		Meta::set($db, App::THREAD_TAG_TABLE, strval(self::$threadTagSchemaVersion));
		
		if (!Util::hasTable($db, App::AUTHOR_TABLE) ||
			intval(Meta::get($db, App::AUTHOR_TABLE, "1")) < 2)
		{
			if (!Util::hasTable($db, App::AUTHOR_TABLE))
			{
				Util::createTableIfNotExists($db, self::$authorSchema, App::AUTHOR_TABLE, array
				(
					App::AUTHOR_TABLE . "ThreadCountIndex" => array("threadCount desc")
				));
				Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf('
					insert into %s
					select name, count(id) as threadCount from %s
					where name != ""
					group by name', App::AUTHOR_TABLE, App::THREAD_ENTRY_TABLE))), array());
			}
			
			foreach (array('
				create trigger %1$sInsertTrigger after insert on %2$s for each row
				begin
					replace into %1$s
					select name, count(id) as threadCount from %2$s
					where name = new.name
					group by name;
				end', '
				create trigger %1$sUpdateTrigger after update on %2$s for each row
				begin
					update %1$s
					set threadCount = threadCount - 1
					where name = old.name;
					
					delete from %1$s where name = old.name and threadCount = 0;
					
					replace into %1$s
					select name, count(id) as threadCount from %2$s
					where name = new.name
					group by name;
				end', '
				create trigger %1$sDeleteTrigger after delete on %2$s for each row
				begin
					update %1$s
					set threadCount = threadCount - 1
					where name = old.name;
					
					delete from %1$s where name = old.name and threadCount = 0;
				end') as $i)
			{
				$i = sprintf($i, App::AUTHOR_TABLE, App::THREAD_ENTRY_TABLE);
				$sl = explode(" ", $i);
				Util::executeStatement(Util::ensureStatement($db, $db->prepare('drop trigger if exists ' . $sl[2])), array(), false);
				Util::executeStatement(Util::ensureStatement($db, $db->prepare($i)), array(), false);
			}
		}
		
		Meta::set($db, App::AUTHOR_TABLE, strval(self::$authorSchemaVersion));
		
		if (!Util::hasTable($db, App::TAG_TABLE) ||
			intval(Meta::get($db, App::TAG_TABLE, "1")) < 2)
		{
			if (!Util::hasTable($db, App::TAG_TABLE))
			{
				Util::createTableIfNotExists($db, self::$tagSchema, App::TAG_TABLE, array
				(
					App::TAG_TABLE . "ThreadCountIndex" => array("threadCount desc")
				));
				Util::executeStatement(Util::ensureStatement($db, $db->prepare(sprintf('
					insert into %s
					select tag, count(id) as threadCount from %s
					group by tag', App::TAG_TABLE, App::THREAD_TAG_TABLE))), array());
			}
			
			foreach (array('
				create trigger %1$sInsertTrigger after insert on %2$s for each row
				begin
					replace into %1$s
					select tag, count(id) as threadCount from %2$s
					where tag = new.tag
					group by tag;
				end', '
				create trigger %1$sUpdateTrigger after update on %2$s for each row
				begin
					update %1$s
					set threadCount = threadCount - 1
					where tag = old.tag;
					
					delete from %1$s where tag = old.tag and threadCount = 0;
					
					replace into %1$s
					select tag, count(id) as threadCount from %2$s
					where tag = new.tag
					group by tag;
				end', '
				create trigger %1$sDeleteTrigger after delete on %2$s for each row
				begin
					update %1$s
					set threadCount = threadCount - 1
					where tag = old.tag;
					
					delete from %1$s where tag = old.tag and threadCount = 0;
				end') as $i)
			{
				$i = sprintf($i, App::TAG_TABLE, App::THREAD_TAG_TABLE);
				$sl = explode(" ", $i);
				Util::executeStatement(Util::ensureStatement($db, $db->prepare('drop trigger if exists ' . $sl[2])), array(), false);
				Util::executeStatement(Util::ensureStatement($db, $db->prepare($i)), array(), false);
			}
		}
		
		Meta::set($db, App::TAG_TABLE, strval(self::$tagSchemaVersion));
	}
	
	private static function query(PDO $db, string $options = "", array $params = array(), array $columns = array("*")): array
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
			foreach ($st->fetchAll(PDO::FETCH_CLASS, "\\Megalopolis\\ThreadEntry") as $i)
				$rt[$i->id] = $i;
			
			return $rt;
		}
		else
			return $st->fetchAll();
	}
	
	/**
	 * @return array<int, string[]>
	 */
	private static function queryTags(PDO $db, string $options = "", array $params = array()): array
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select id, tag, position from %s
			%s',
			App::THREAD_TAG_TABLE,
			trim($options)
		)));
		if ($st === null) return array();

		$st->execute($params);
		$rt = array();
		
		foreach ($st->fetchAll() as $i)
		{
			if (!isset($rt[$i["id"]]))
				$rt[$i["id"]] = array();
			
			$rt[$i["id"]][$i["tag"]] = intval($i["position"]);
		}
		
		return array_map(function($_) { asort($_); return array_keys($_); }, $rt);
	}
	
	/**
	 * @return int[]
	 */
	private static function getAllMegalithEntryIDs(int $latest): array
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
				
				foreach (array_map(function($_) { return mb_convert_encoding($_, "UTF-8", "Windows-31J"); }, Util::readLines($i)) as $j)
					if ($id = strstr($j, "<>", true))
						$rt[] = intval($id);
			}
		
		return $rt;
	}
	
	/**
	 * @return ThreadEntry[]
	 */
	private static function getAllMegalithEntries(int $latest, bool $getSize = false): array
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
				
				foreach (array_map(function($_) { return mb_convert_encoding($_, "UTF-8", "Windows-31J"); }, Util::readLines($i)) as $j)
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
	
	/**
	 * @return ThreadEntry[]
	 */
	private static function searchAllMegalithEntries(PDO $db, array $query): array
	{
		$rt = array();
		
		foreach (self::getAllMegalithEntries(Board::getLatestSubject($db)) as $i)
		{
			$matches = true;
			
			if (isset($query["title"]) && $query["title"])
				foreach ($query["title"] as $j)
					$matches = $matches && $i->title !== null && mb_strpos($i->title, $j) !== false;
			
			if ($matches && isset($query["name"]) && $query["name"] && Configuration::$instance->showName[Configuration::ON_SUBJECT])
				foreach ($query["name"] as $j)
					$matches = $matches && $i->name !== null && mb_strpos($i->name, $j) !== false;
			
			if ($matches && isset($query["tag"]) && $query["tag"] && Configuration::$instance->showTags[Configuration::ON_SUBJECT])
			{
				$tags = implode(" ", $i->tags);
				
				foreach ($query["tag"] as $j)
					$matches = $matches && mb_strpos($tags, $j) !== false;
			}
			
			if ($matches && isset($query["eval"]) && $query["eval"])
				$matches = $i->evaluationCount >= $query["eval"][0] && $i->evaluationCount <= $query["eval"][1];
			
			if ($matches && isset($query["points"]) && $query["points"])
				$matches = $i->points >= $query["points"][0] && $i->points <= $query["points"][1];
			
			if ($matches && isset($query["dateTime"]) && $query["dateTime"])
				$matches = $i->dateTime >= $query["dateTime"][0] && $i->dateTime <= $query["dateTime"][1];
			
			$body = $matches && is_file($aft = "Megalith/dat/{$i->id}.dat") ? mb_convert_encoding(implode("\r\n", Util::readLines($aft)), "UTF-8", "Windows-31J") : "";
			$afterword = $matches && is_file($aft = "Megalith/aft/{$i->id}.aft.dat") ? mb_convert_encoding(implode("\r\n", Util::readLines($aft)), "UTF-8", "Windows-31J") : "";
			
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
						$i->title !== null && mb_strpos($i->title, $j) !== false ||
						$i->name !== null && mb_strpos($i->name, $j) !== false ||
						mb_strpos($body, $j) !== false ||
						mb_strpos($afterword, $j) !== false
					);
			
			if ($matches)
				$rt[$i->id] = $i;
		}

		return $rt;
	}

	/**
	 * @return int[]
	 */
	static function getMegalithEntryIDsBySubject(PDO $db, int $subject): array
	{
		$rt = array();
		
		if (is_file($path = "Megalith/sub/" . ($subject == Board::getLatestSubject($db) ? "subject.txt" : "subject{$subject}.txt")))
			foreach (array_reverse(Util::readLines($path)) as $i)
				if (count($line = explode("<>", $i)) > 2)
					$rt[] = intval(str_replace(".dat", "", $line[0]));
		
		return $rt;
	}
	
	/**
	 * @return ThreadEntry[]
	 */
	private static function getMegalithEntriesBySubject(PDO $db, int $subject): array
	{
		$rt = array();
		
		if (is_file($path = "Megalith/sub/" . ($subject == Board::getLatestSubject($db) ? "subject.txt" : "subject{$subject}.txt")))
			foreach (array_reverse(Util::readLines($path)) as $i)
			{
				$entry = Util::convertLineToThreadEntry(mb_convert_encoding($i, "UTF-8", "Windows-31J"));
				
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
	 * @return ThreadEntry[]
	 */
	static function getEntriesBySubject(PDO $db, int $subject, int $order = Board::ORDER_DESCEND): array
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
			implode(", ", array_map(fn(ThreadEntry $_) => $_->id, $rt))
		));
		
		if (Configuration::$instance->convertOnDemand)
			$rt += self::getMegalithEntriesBySubject($db, $subject);
		
		$rt = self::processResultEntries($db, $rt);
		
		if ($order == Board::ORDER_DESCEND)
			krsort($rt);
		else
			ksort($rt);

		return $rt;
	}

	/**
	 * @return int[]
	 */
	static function getEntryIDsBySubject(PDO $db, int $subject): array
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select id from %s
			where subject = ?',
			App::THREAD_ENTRY_TABLE
		)));
		Util::executeStatement($st, array($subject));
		
		if (!$st) return array();

		return array_map("intval", $st->fetchAll(PDO::FETCH_COLUMN, 0));
	}
	
	/**
	 * @return ThreadEntry[]
	 */
	static function getEntriesByName(PDO $db, string $name, int $offset = 0, ?int $limit = null, int $order = Board::ORDER_DESCEND, ?int &$foundItems = null): array
	{
		$isMysql = Configuration::$instance->dataStore instanceof MySQLDataStore;
		$rt = array();
		$sql = sprintf
		('
			select %s * from %s as t
			left join %s as e on e.id = t.id
			where name = ?
			order by t.id %s
			%s',
			$isMysql ? "sql_calc_found_rows" : "",
			App::THREAD_ENTRY_TABLE,
			App::THREAD_EVALUATION_TABLE,
			$order == Board::ORDER_ASCEND ? "asc" : "desc",
			is_null($limit) ? "" : "limit {$limit} offset {$offset}"
		);
		Util::executeStatement($st = Util::ensureStatement($db, $db->prepare($sql)), array($name));
		
		if (!$st) return array();

		foreach ($st->fetchAll(PDO::FETCH_CLASS, "\\Megalopolis\\ThreadEntry") as $i)
			$rt[$i->id] = $i;
		
		if ($isMysql)
		{
			Util::executeStatement($st2 = Util::ensureStatement($db, $db->prepare("select found_rows()")));
			if ($st2)
			{
				$foundItems = $st2->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
				$foundItems = intval(array_pop($foundItems));
			}
		}
		
		$tags = self::queryTags($db, sprintf
		('
			where id in (%s)',
			implode(", ", array_map(fn(ThreadEntry $_) => $_->id, $rt))
		));
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
		{
			foreach (self::getAllMegalithEntries(Board::getLatestSubject($db), true) as $i)
				if (!isset($rt[$i->id]) && $i->name == $name)
					$rt[$i->id] = $i;
			
			krsort($rt);
		}
		
		$rt = self::processResultEntries($db, $rt);
		
		return $rt;
	}
	
	/**
	 * @return ThreadEntry[]
	 */
	static function getEntriesByTag(PDO $db, string $tag, int $offset = 0, ?int $limit = null, int $order = Board::ORDER_DESCEND, ?int &$foundItems = null): array
	{
		$isMysql = Configuration::$instance->dataStore instanceof MySQLDataStore;
		$rt = array();
		$sql = sprintf
		('
			select %s * from %s as tt
			join %s as t on t.id = tt.id and tag = ?
			left join %s as e on e.id = tt.id
			order by tt.id %s
			%s',
			$isMysql ? "sql_calc_found_rows" : "",
			App::THREAD_TAG_TABLE,
			App::THREAD_ENTRY_TABLE,
			App::THREAD_EVALUATION_TABLE,
			$order == Board::ORDER_ASCEND ? "asc" : "desc",
			is_null($limit) ? "" : "limit {$limit} offset {$offset}"
		);
		Util::executeStatement($st = Util::ensureStatement($db, $db->prepare($sql)), array($tag));
		
		if (!$st) return array();

		foreach ($st->fetchAll(PDO::FETCH_CLASS, "\\Megalopolis\\ThreadEntry") as $i)
			$rt[$i->id] = $i;
		
		if ($isMysql)
		{
			Util::executeStatement($st2 = Util::ensureStatement($db, $db->prepare("select found_rows()")));
			if ($st2)
			{
				$foundItems = $st2->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
				$foundItems = intval(array_pop($foundItems));
			}
		}
		
		$tags = self::queryTags($db, sprintf
		('
			where id in (%s)',
			implode(", ", array_keys($rt))
		));
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
		{
			foreach (self::getAllMegalithEntries(Board::getLatestSubject($db)) as $i)
				if (!isset($rt[$i->id]) && in_array($tag, $i->tags))
					$rt[$i->id] = $i;
			
			krsort($rt);
		}
		
		$rt = self::processResultEntries($db, $rt);
		
		return $rt;
	}
	
	static function getNameCount(PDO $db): int
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select count(name) from %s',
			App::AUTHOR_TABLE
		)));
		Util::executeStatement($st);
		$rt = $st?->fetchAll(PDO::FETCH_COLUMN) ?: array(0);
		
		return $rt[0];
	}
	
	static function getTagCount(PDO $db): int
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select count(tag) from %s',
			App::TAG_TABLE
		)));
		Util::executeStatement($st);
		$rt = $st?->fetchAll(PDO::FETCH_COLUMN) ?: array(0);
		
		return $rt[0];
	}

	/**
	 * @return array<string, int>
	 */
	static function getNames(PDO $db, int $offset = 0, ?int $limit = null, int $order = Board::ORDER_DESCEND): array
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select name, threadCount from %s
			order by threadCount %s
			%s',
			App::AUTHOR_TABLE,
			$order == Board::ORDER_ASCEND ? "asc" : "desc",
			is_null($limit) ? "" : "limit {$limit} offset {$offset}"
		)));
		Util::executeStatement($st);
		$rt = $st?->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_GROUP) ?? array();
		$rt = array_map(fn(array $_): int => $_[0], $rt);
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
		{
			foreach (self::getAllMegalithEntries(0) as $i)
				if (isset($i->name))
					if (isset($rt[$i->name]))
						$rt[$i->name]++;
					else
						$rt[$i->name] = 1;
			
			uasort($rt, fn(int $x, int $y) => $y <=> $x);
		}
		
		return $rt;
	}

	/**
	 * @return array<string, int>
	 */
	static function getTags(PDO $db, int $offset = 0, ?int $limit = null, int $order = Board::ORDER_DESCEND): array
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select tag, threadCount from %s
			order by threadCount %s
			%s',
			App::TAG_TABLE,
			$order == Board::ORDER_ASCEND ? "asc" : "desc",
			is_null($limit) ? "" : "limit {$limit} offset {$offset}"
		)));
		Util::executeStatement($st);
		$rt = $st?->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_GROUP) ?? array();
		$rt = array_map(fn(array $_): int => $_[0], $rt);
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
		{
			foreach (self::getAllMegalithEntries(0) as $i)
				foreach ($i->tags as $j)
					if (isset($rt[$j]))
						$rt[$j]++;
					else
						$rt[$j] = 1;
			
			uasort($rt, fn(int $x, int $y) => $y <=> $x);
		}
		
		return $rt;
	}
	
	static function getEntryCountByName(PDO $db, string $name): int
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select count(1) from %s
			where name = ?',
			App::THREAD_ENTRY_TABLE
		)));
		Util::executeStatement($st, array($name));
		$rt = ($st?->fetchAll(PDO::FETCH_COLUMN) ?? array(0))[0];
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
			foreach (self::getAllMegalithEntries(0) as $i)
				if ($i->name == $name)
					$rt++;
		
		return $rt;
	}
	
	static function getEntryCountByTag(PDO $db, string $tag): int
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			select count(1) from %s
			where tag = ?',
			App::THREAD_TAG_TABLE
		)));
		Util::executeStatement($st, array($tag));
		$rt = ($st?->fetchAll(PDO::FETCH_COLUMN) ?? array(0))[0];
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
			foreach (self::getAllMegalithEntries(0) as $i)
				if (in_array($tag, $i->tags))
					$rt++;
		
		return $rt;
	}
	
	static function getRandomEntry(PDO $db, PDO $idb): ?ThreadEntry
	{
		$count = SearchIndex::getEntryCount($idb);
		
		if ($count !== null)
			$st = $db->prepare(sprintf
			('
				select id from %s
				limit %d, 1',
				App::THREAD_ENTRY_TABLE,
				mt_rand(0, $count - 1)
			));
		else
			$st = $db->prepare(sprintf
			('
				select id from %s',
				App::THREAD_ENTRY_TABLE
			));
		
		Util::executeStatement(Util::ensureStatement($db, $st));
		$rt = $st->fetchAll(PDO::FETCH_COLUMN);
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
			$rt = array_unique(array_merge($rt, self::getAllMegalithEntryIDs(0)));
		
		if ($rt)
			return self::load($db, $rt[array_rand($rt)]);
		else
			return null;
	}
	
	/**
	 * @return array{maxDateTime: ?int, minDateTime: ?int, maxEval: ?int, minEval: ?int, maxPoints: ?int, minPoints: ?int}
	 */
	static function getMaxMinValues(PDO $db): array
	{
		$rt = array
		(
			"maxDateTime" => null,
			"minDateTime" => null,
			"maxEval" => null,
			"minEval" => null,
			"maxPoints" => null,
			"minPoints" => null,
		);

		if (Configuration::$instance->dataStore instanceof SQLiteDataStore)
			$st = Util::ensureStatement($db, $db->prepare(sprintf
			('
				select
					max(a.evaluationCount) as maxEval,
					min(a.evaluationCount) as minEval,
					max(a.points) as maxPoints,
					min(a.points) as minPoints,
					max(c.dateTime) as maxDateTime,
					min(c.dateTime) as minDateTime
					from %2$s as a, %1$s as c',
				App::THREAD_ENTRY_TABLE,
				App::THREAD_EVALUATION_TABLE
			)));
		else
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
		
		if ($st)
			foreach ($st->fetch() as $k => $v)
				$rt[$k] = (int)$v;
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
			foreach (self::getAllMegalithEntries(0) as $i)
				$rt = array
				(
					"maxDateTime" => max($rt["maxDateTime"] ?? $i->dateTime, $i->dateTime),
					"minDateTime" => min($rt["minDateTime"] ?? $i->dateTime, $i->dateTime),
					"maxEval" => max($rt["maxEval"] ?? $i->evaluationCount, $i->evaluationCount),
					"minEval" => min($rt["minEval"] ?? $i->evaluationCount, $i->evaluationCount),
					"maxPoints" => max($rt["maxPoints"] ?? $i->points, $i->points),
					"minPoints" => min($rt["minPoints"] ?? $i->points, $i->points),
				);
		
		return $rt;
	}

	/**
	 * @param array{0: int, 1: int} $subjectRange
	 * @param ("thread"|"comment"|"evaluation")[]|string[] $target
	 * @return ThreadEntry[]
	 */
	static function getEntriesByHost(PDO $db, string $host, array $subjectRange, array $target, int $offset = 0, ?int $limit = null, int $order = Board::ORDER_DESCEND, ?int &$foundItems = null): array
	{
		$isMysql = Configuration::$instance->dataStore instanceof MySQLDataStore;
		$rt = array();
		$sql = sprintf
		('
			from (select * from %s where subject between :begin and :end) as t
			left join %s as e on e.id = t.id
			where host like :host %s %s',
			App::THREAD_ENTRY_TABLE,
			App::THREAD_EVALUATION_TABLE,
			in_array("comment", $target) ? 'or exists (select * from comment as c where c.entryID = t.id and c.host like :host limit 1)' : "",
			in_array("evaluation", $target) ? 'or exists (select * from evaluation as ee where ee.entryID = t.id and ee.host like :host limit 1)' : ""
		);
		Util::executeStatement($st = Util::ensureStatement($db, $db->prepare
		(
			"select " . ($isMysql ? "sql_calc_found_rows " : " ") .
				"* {$sql} order by t.id " . ($order == Board::ORDER_ASCEND ? "asc" : "desc") .
				(is_null($limit) ? "" : " limit {$limit} offset {$offset}")
		)), array
		(
			":begin" => $subjectRange[0],
			":end" => $subjectRange[1],
			":host" => str_replace("*", "%", $host)
		));
		
		if (!$st) return array();

		foreach ($st->fetchAll(PDO::FETCH_CLASS, "\\Megalopolis\\ThreadEntry") as $i)
			$rt[$i->id] = $i;
		
		if ($isMysql)
		{
			Util::executeStatement($st2 = Util::ensureStatement($db, $db->prepare("select found_rows()")));
			if ($st2)
			{
				$foundItems = $st2->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
				$foundItems = intval(array_pop($foundItems));
			}
		}
		else
		{
			Util::executeStatement($st2 = Util::ensureStatement($db, $db->prepare("select count(*) {$sql}")));
			if ($st2)
			{
				$foundItems = $st2->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
				$foundItems = intval(array_pop($foundItems));
			}
		}
		
		if (Configuration::$instance->convertOnDemand &&
			is_dir("Megalith/sub"))
		{
			foreach (range($subjectRange[0], $subjectRange[1] - $subjectRange[0], 1) as $subject)
				foreach (self::getMegalithEntriesBySubject($db, $subject) as $entry)
					if (!isset($rt[$entry->id]))
					{
						if ($entry->host !== null && Util::wildcard($host, $entry->host))
							$rt[$entry->id] = $entry;
						else if (is_file("Megalith/com/{$entry->id}"))
							foreach (Util::convertLinesToCommentsAndEvaluations($entry->id, array_map(fn($x) => mb_convert_encoding($x, "UTF-8", "Windows-31J"), Util::readLines("Megalith/com/{$entry->id}"))) as $commentOrEvaluation)
								if (isset($commentOrEvaluation->host) && Util::wildcard($host, $commentOrEvaluation->host))
									$rt[$entry->id] = $entry;
					}
			
			krsort($rt);
		}
		
		$rt = self::processResultEntries($db, $rt);
		
		return $rt;
	}
	
	/**
	 * @template T as int
	 * @param T $option
	 * @return null|array{result: ThreadEntry[], count: int}|ThreadEntry
	 * @psalm-return ($option is self::SEARCH_RANDOM ? (?ThreadEntry) : array{result: ThreadEntry[], count: int})
	 */
	static function search(PDO $db, PDO $idb, array $query, int $offset = 0, ?int $limit = null, int $option = self::SEARCH_DESCENDING, string $sort = "1")
	{
		$ids = null;
		$whereString = "";
		
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
		
		if (is_array($ids) && !$ids)
			$count = array(0);
		else
		{
			$where = array
			(
				!empty($ids) ? App::THREAD_ENTRY_TABLE . ".id in (" . implode(", ", $ids) . ")" : null,
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
			/** @var int[] */
			$count = $st?->fetch() ?? array(0);
		}
		
		if ($option == self::SEARCH_RANDOM)
		{
			if (is_array($ids) && !$ids)
				return null;
			else
				$rt = self::query($db, $whereString, array(), array(App::THREAD_ENTRY_TABLE . ".id"));
			
			if (Configuration::$instance->convertOnDemand &&
				is_dir("Megalith/sub"))
				$rt = array_merge($rt, self::searchAllMegalithEntries($db, $query));
			
			$val = $rt[array_rand($rt)];
			
			return is_array($val) ? ThreadEntry::load($db, $val[0]) : $val;
		}
		else
		{
			if (is_array($ids) && !$ids)
				$rt = array();
			else
				$rt = self::query($db, "{$whereString} order by {$sort} " . ($option == self::SEARCH_DESCENDING ? "desc" : "asc") . ($limit ? " limit {$limit} offset {$offset}" : ""));
			
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
			
			$rt = self::processResultEntries($db, $rt);
			
			return array
			(
				"result" => $rt,
				"count" => $count[0]
			);
		}
	}
	
	/**
	 * @param ThreadEntry[] $rt
	 * @return ThreadEntry[]
	 */
	private static function processResultEntries(PDO $db, array $rt): array
	{
		if (!$rt)
			return $rt;
		
		$tags = self::queryTags($db, sprintf
		('
			where id in (%s)',
			implode(", ", array_map(function($_) { return $_->id; }, $rt))
		));
		
		foreach ($rt as $i)
		{
			if (!$i->tags && isset($tags[$i->id]))
				$i->tags = $tags[$i->id];
			
			$i->commentedEvaluationCount = $i->commentCount - ($i->responseCount - $i->evaluationCount);
			$i->lastUpdate = intval($i->lastUpdate);
			
			if (is_null($i->responseLastUpdate))
				$i->responseLastUpdate = $i->lastUpdate;
			else
				$i->responseLastUpdate = intval($i->responseLastUpdate);
			
			$i->calculateRate();
			$i->loaded = true;
		}
		
		return $rt;
	}
	
	/**
	 * @param int[] $ids
	 */
	static function deleteDirect(PDO $db, ?PDO $idb, array $ids): void
	{
		$idString = implode(", ", array_map('intval', $ids));
		
		foreach (array
		(
			App::THREAD_ENTRY_TABLE,
			App::THREAD_EVALUATION_TABLE,
			App::THREAD_PASSWORD_TABLE,
			App::THREAD_STYLE_TABLE,
			App::THREAD_TABLE,
			App::THREAD_TAG_TABLE,
		) as $i)
			Util::executeStatement(Util::ensureStatement($db, $db->prepare("delete from {$i} where id in ({$idString})")));
		
		foreach (array
		(
			App::EVALUATION_TABLE,
			App::COMMENT_TABLE,
		) as $i)
			Util::executeStatement(Util::ensureStatement($db, $db->prepare("delete from {$i} where entryID in ({$idString})")));
		
		if ($idb != null)
			SearchIndex::unregister($idb, $ids);
	}
}
?>
