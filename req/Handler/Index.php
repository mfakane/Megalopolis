<?php
namespace Megalopolis;

class IndexHandler extends Handler
{
	static IndexHandler $instance;
	
	public int $subject = 0;
	public int $subjectCount = 0;
	public int $page = 1;
	public int $pageCount = 0;
	/** @var array<int, ThreadEntry>|null */
	public ?array $entries = null;
	/** @var array{view: ThreadEntry[], evaluation: ThreadEntry[]}|null */
	public ?array $recentEntries = null;
	public ?int $entryCount = null;
	public ?int $lastUpdate = null;
	
	function index(string $_subject = "0", string $_id = "0"): bool
	{
		if (intval($_id))
		{
			$args = func_get_args();
			
			return App::callHandler("read", "index", $args);
		}
		else if (isset($_GET["mode"]) || isset($_GET["log"]))
		{
			$args = func_get_args();
		
			return App::callHandler("Megalith", "parseQuery", $args);
		}
		
		$subject = intval($_subject);
		
		Auth::cleanSession(!Auth::hasSession(true));
		
		if (!Auth::hasToken())
			Auth::createToken();
		
		$db = App::openDB();
		$idb = App::openDB(App::INDEX_DATABASE);

		if ($admin = self::param("admin", ""))
		{
			Auth::ensureToken();
			Auth::createToken();
			
			if (!Util::hashEquals(Configuration::$instance->adminHash ?? "", Auth::login(true)))
				Auth::loginError("管理者パスワードが一致しません");
			
			$ids = array_map("intval", self::paramAsArray("id", []));
			$db->beginTransaction();
			
			switch (Util::escapeInput($admin))
			{
				case "unpost":
					if ($db !== $idb)
						$idb->beginTransaction();
					
					ThreadEntry::deleteDirect($db, $idb, $ids);
					Board::setLastUpdate($db, $subject);
					
					if ($db !== $idb)
						$idb->commit();
					
					break;
			}
			
			$db->commit();
		}
		
		if ($subject == 0)
			$subject = Board::getLatestSubject($db);
		else if ($subject > Board::getLatestSubject($db))
			throw new ApplicationException("指定された番号 {$subject} の作品集は存在しません", 404);
		
		$this->entries = ThreadEntry::getEntriesBySubject($db, $subject);
		
		if (!($this->lastUpdate = Board::getLastUpdate($db, $subject)))
			$this->lastUpdate = max(array_map(fn(ThreadEntry $x) => $x->getLatestLastUpdate(), $this->entries) + array(0));
		
		$this->subject = $subject;
		$this->subjectCount = Board::getLatestSubject($db);
		$this->entryCount = Board::getEntryCount($db, $idb);
		
		if ($this->lastUpdate)
		{
			$updatePeriodInSeconds = Configuration::$instance->updatePeriod * 24 * 60 * 60;
			$hash = implode(",", array_map(fn(ThreadEntry $x) => $x->id . ":" . (time() - $x->getLatestLastUpdate() < $updatePeriodInSeconds ? "t" : "n"), $this->entries));
			
			if (Util::isCachedByBrowser($this->lastUpdate, Cookie::getCookie(Cookie::LIST_TYPE_KEY, "") . Cookie::getCookie(Cookie::LIST_VISIBILITY_KEY, "") . $hash))
				Visualizer::notModified();
		}
		
		App::closeDB($idb);
		App::closeDB($db);
		
		switch (App::$handlerType)
		{
			case "json":
				return Visualizer::json(array
				(
					"entries" => array_values(array_map(fn($x) => $x->toArray(Configuration::ON_SUBJECT), $this->entries)),
					"subject" => $this->subject,
					"subjectCount" => $this->subjectCount
				));
			case "csv":
				return self::toCSV($this->entries);
			case "atom":
				return Visualizer::visualize("Index/Index.Atom", 200, "application/atom+xml");
			case "rss":
				return Visualizer::visualize("Index/Index.Rss", 200, "application/rss+xml");
			default:
				return Visualizer::visualize();
		}
	}
	
	function recent(): bool
	{
		$db = App::openDB();
		$this->recentEntries = array
		(
			"view" => array(),
			"evaluation" => array(),
		);
		
		if ($view = Cookie::getCookie(Cookie::VIEW_HISTORY_KEY))
			foreach (explode(",", $view, Configuration::$instance->maxHistory) as $i)
				if ($entry = ThreadEntry::load($db, intval($i)))
					$this->recentEntries["view"][] = $entry;
		
		if ($evaluation = Cookie::getCookie(Cookie::EVALUATION_HISTORY_KEY))
			foreach (explode(",", $evaluation, Configuration::$instance->maxHistory) as $i)
				if ($entry = ThreadEntry::load($db, intval($i)))
					$this->recentEntries["evaluation"][] = $entry;
		
		App::closeDB($db);
		
		switch (App::$handlerType)
		{
			case "json":
				return Visualizer::json(array
				(
					"view" => array_map(fn($x) => $x->toArray(Configuration::ON_SUBJECT), $this->recentEntries["view"]),
					"evaluation" => array_map(fn($x) => $x->toArray(Configuration::ON_SUBJECT), $this->recentEntries["evaluation"]),
				));
			default:
				return Visualizer::visualize();
		}
	}
	
	function search(): bool
	{
		if (!Configuration::$instance->showTitle[Configuration::ON_SUBJECT] && !Auth::hasSession(true))
			throw new ApplicationException("作品の閲覧は許可されていません", 403);
		
		if (!Configuration::$instance->useSearch && !Auth::hasSession(true))
			throw new ApplicationException("検索は許可されていません", 403);
		
		if (!is_null($mode = self::param("mode")))
			switch ($mode)
			{
				case "query":
					break;
				case "title":
					$_GET["title"] = self::param("query");
					unset($_GET["query"]);
					
					break;
				case "name":
					$_GET["name"] = self::param("query");
					unset($_GET["query"]);
					
					break;
				case "tag":
					$_GET["tag"] = self::param("query");
					unset($_GET["query"]);
					
					break;
			}
		
		$this->page = max(intval(self::param("p", "1")), 1);
		
		switch ($s = self::param("s", ""))
		{
			case "title":
			case "name":
				$sort = array(ThreadEntry::SEARCH_ASCENDING, $s);
				
				break;
			case "points":
			case "rate":
			case "size":
			case "dateTime":
				$sort = array(ThreadEntry::SEARCH_DESCENDING, $s);
				
				break;
			default:
				$sort = array(ThreadEntry::SEARCH_DESCENDING, "1");
				
				break;
		}
		
		$rt = ["count" => 0];
		$entries = [];
		$db = App::openDB();
		$idb = App::openDB(App::INDEX_DATABASE);
		$vals = ThreadEntry::getMaxMinValues($db);
		$query = array
		(
			"query" => Util::splitTags(self::param("query", "")),
			"title" => Util::splitTags(self::param("title", "")),
			"name" => Util::splitTags(self::param("name", "")),
			"tag" => Util::splitTags(self::param("tags", self::param("tag", ""))),
			"eval" => array
			(
				!Util::isEmpty(self::param("evalBegin", "")) ? intval(self::param("evalBegin")) : $vals["minEval"] ?? 0,
				!Util::isEmpty(self::param("evalEnd", "")) ? intval(self::param("evalEnd")) : $vals["maxEval"] ?? 0
			),
			"points" => array
			(
				!Util::isEmpty(self::param("pointsBegin", "")) ? intval(self::param("pointsBegin")) : $vals["minPoints"] ?? 0,
				!Util::isEmpty(self::param("pointsBegin", "")) ? intval(self::param("pointsEnd")) : $vals["maxPoints"] ?? 0
			),
			"dateTime" => array
			(
				self::param("dateTimeBegin", ""),
				self::param("dateTimeEnd", "")
			)
		);
		
		if (substr_count($query["dateTime"][0], "-") == 2)
		{
			$d = explode("-", $query["dateTime"][0]);
			$query["dateTime"][0] = mktime(0, 0, 0, intval($d[1]), intval($d[2]), intval($d[0]));
		}
		else
			$query["dateTime"][0] = $vals["minDateTime"] ?? 0;
		
		if (substr_count($query["dateTime"][1], "-") == 2)
		{
			$d = explode("-", $query["dateTime"][1]);
			$query["dateTime"][1] = mktime(23, 59, 59, intval($d[1]), intval($d[2]), intval($d[0]));
		}
		else
			$query["dateTime"][1] = $vals["maxDateTime"] ?? 0;
		
		if ($query["query"] ||
			$query["title"] ||
			$query["name"] ||
			$query["tag"] ||
			$query["eval"][0] != $vals["minEval"] ||
			$query["eval"][1] != $vals["maxEval"] ||
			$query["points"][0] != $vals["minPoints"] ||
			$query["points"][1] != $vals["maxPoints"] ||
			date("Y-m-d", $query["dateTime"][0]) != date("Y-m-d", $vals["minDateTime"] ?? 0) ||
			date("Y-m-d", $query["dateTime"][1]) != date("Y-m-d", $vals["maxDateTime"] ?? 0))
		{
			if (self::param("random") == "true")
			{
				$rt = ThreadEntry::search($db, $idb, $query, 0, 0, ThreadEntry::SEARCH_RANDOM);
				
				if ($rt)
					return Visualizer::redirect("{$rt->subject}/{$rt->id}");
			}
			
			$rt = ThreadEntry::search($db, $idb, $query, ($this->page - 1) * Configuration::$instance->searchPaging, Configuration::$instance->searchPaging, $sort[0], $sort[1]);
			$this->pageCount = (int)ceil($rt["count"] / Configuration::$instance->searchPaging);
			$entries = $rt["result"];
		}
		
		if (!Auth::hasToken())
			Auth::createToken();
		
		if (($admin = self::param("admin", "")) && $entries)
		{
			Auth::ensureToken();
			Auth::createToken();
			
			if (!Util::hashEquals(Configuration::$instance->adminHash ?? "", Auth::login(true)))
				Auth::loginError("管理者パスワードが一致しません");
			
			$ids = array_map("intval", self::paramAsArray("id", []));
			$db->beginTransaction();
					
			if ($db !== $idb)
				$idb->beginTransaction();
			
			switch ($mode = Util::escapeInput($admin))
			{
				case "unpost":
					ThreadEntry::deleteDirect($db, $idb, $ids);
					$subjects = array_map(fn($x) => $x->subject, array_intersect_key($entries, array_flip($ids)));

					foreach (array_unique($subjects) as $subject)
						Board::setLastUpdate($db, $subject);
					
					foreach ($ids as $i)
						unset($entries[$i]);
					
					$rt["count"] -= count($ids);
					
					break;
			}
			
			if ($db !== $idb)
				$idb->commit();
			
			$db->commit();
		}

		$this->entries = $entries;
		
		App::closeDB($idb);
		App::closeDB($db);
		Visualizer::$data = array
		(
			"count" => $rt["count"],
			"evalBegin" => $query["eval"][0],
			"evalEnd" => $query["eval"][1],
			"evalMin" => $vals["minEval"],
			"evalMax" => $vals["maxEval"],
			"pointsBegin" => $query["points"][0],
			"pointsEnd" => $query["points"][1],
			"pointsMin" => $vals["minPoints"],
			"pointsMax" => $vals["maxPoints"],
			"dateTimeBegin" => date("Y-m-d", $query["dateTime"][0]),
			"dateTimeEnd" => date("Y-m-d", $query["dateTime"][1]),
			"dateTimeMin" => date("Y-m-d", $vals["minDateTime"] ?? 0),
			"dateTimeMax" => date("Y-m-d", $vals["maxDateTime"] ?? 0)
		);
		
		switch (App::$handlerType)
		{
			case "json":
				return Visualizer::json(array
				(
					"entries" => array_values(array_map(fn($x) => $x->toArray(Configuration::ON_SUBJECT), $this->entries)),
					"page" => $this->page,
					"pageCount" => $this->pageCount
				));
			case "csv":
				return self::toCSV($this->entries);
			default:
				return Visualizer::visualize();
		}
	}
	
	/**
	 * @template T as string|null
	 * @param T $value
	 * @return ?string
	 * @psalm-return (T is string ? string : ?string)
	 */
	static function param(string $name, ?string $value = null): ?string
	{
		if (isset($_GET[$name]))
			if (is_array($_GET[$name]))
				return implode(",", array_map(fn($x) => Util::escapeInput(is_array($x) ? implode(",", $x) : $x), $_GET[$name]));
			else
				return Util::escapeInput($_GET[$name]);
		else
			return $value;
	}

	/**
	 * @template T as string[]|null
	 * @param T $value
	 * @return string[]|null
	 * @psalm-return (T is array ? string[] : string[]|null)
	 */
	static function paramAsArray(string $name, ?array $value = null): ?array
	{
		if (isset($_GET[$name]))
			if (is_array($_GET[$name]))
				return array_map(fn($x) => Util::escapeInput(is_array($x) ? implode(",", $x) : $x), $_GET[$name]);
			else
				return [Util::escapeInput($_GET[$name])];
		else
			return $value;
	}

	
	/**
	 * @template T as string|null
	 * @param T $value
	 * @return ?string
	 * @psalm-return (T is string ? string : ?string)
	 */
	static function postParam(string $name, ?string $value = null): ?string
	{
		if (isset($_POST[$name]))
			if (is_array($_POST[$name]))
				return implode(",", array_map(fn($x) => Util::escapeInput(is_array($x) ? implode(",", $x) : $x), $_POST[$name]));
			else
				return Util::escapeInput($_POST[$name]);
		else
			return $value;
	}

	/**
	 * @template T as string[]|null
	 * @param T $value
	 * @return string[]|null
	 * @psalm-return (T is array ? string[] : string[]|null)
	 */
	static function postParamAsArray(string $name, ?array $value = null): ?array
	{
		if (isset($_POST[$name]))
			if (is_array($_POST[$name]))
				return array_map(fn($x) => Util::escapeInput(is_array($x) ? implode(",", $x) : $x), $_POST[$name]);
			else
				return [Util::escapeInput($_POST[$name])];
		else
			return $value;
	}

	function _new(): bool
	{
		$args = func_get_args();
		
		return App::callHandler("read", "_new", $args);
	}
	
	function edit(): bool
	{
		$args = func_get_args();
		
		return App::callHandler("read", "edit", $args);
	}
	
	function post(): bool
	{
		$args = func_get_args();
		
		return App::callHandler("read", "post", $args);
	}
	
	function unpost(): bool
	{
		$args = func_get_args();
		
		return App::callHandler("read", "unpost", $args);
	}
	
	function comment(): bool
	{
		$args = func_get_args();
		
		return App::callHandler("read", "comment", $args);
	}
	
	function uncomment(): bool
	{
		$args = func_get_args();
		
		return App::callHandler("read", "uncomment", $args);
	}
	
	function evaluate(): bool
	{
		$args = func_get_args();
		
		return App::callHandler("read", "evaluate", $args);
	}
	
	function unevaluate(): bool
	{
		$args = func_get_args();
		
		return App::callHandler("read", "unevaluate", $args);
	}
	
	function config(): bool
	{
		$args = func_get_args();
		
		return App::callHandler("util", "config", $args);
	}
	
	function random(): bool
	{
		$db = App::openDB();
		$idb = App::openDB(App::INDEX_DATABASE);
		$entry = ThreadEntry::getRandomEntry($db, $idb);
		App::closeDB($idb);
		App::closeDB($db);
		
		if ($entry)
			return Visualizer::redirect("{$entry->subject}/{$entry->id}");
		else
			return Visualizer::redirect();
	}
	
	function author(): bool
	{
		$args = func_get_args();
		$page = $args && ctype_digit($args[count($args) - 1]) ? max(intval(array_pop($args)) - 1, 0) : 0;
		$isNameList = !$args;
		$name = $isNameList ? null : Util::escapeInput(implode("/", $args));
		
		if (!Auth::hasSession(true))
			if (!Configuration::$instance->showTitle[Configuration::ON_SUBJECT])
				throw new ApplicationException("作品の閲覧は許可されていません", 403);
			else if (!Configuration::$instance->showName[Configuration::ON_SUBJECT])
				throw new ApplicationException("作者の閲覧は許可されていません", 403);
		
		$db = App::openDB();
		$this->entries = array();
		
		if ($isNameList || !isset($name))
		{
			$pageCount = (int)ceil(($this->entryCount = ThreadEntry::getNameCount($db)) / Configuration::$instance->tagListing);
			Visualizer::$data = ThreadEntry::getNames($db, $page * Configuration::$instance->tagListing, Configuration::$instance->tagListing);
		}
		else
		{
			if ($name == "random")
			{
				$arr = ThreadEntry::getNames($db);
				App::closeDB($db);
				
				return Visualizer::redirect("author" . ($arr ? "/" . array_rand($arr) : ""));
			}
			
			if (strstr($name, "/random") == "/random")
			{
				$name = substr($name, 0, -strlen("/random"));
				$this->entries = ThreadEntry::getEntriesByName($db, $name);
				
				if ($this->entries)
				{
					$entry = $this->entries[array_rand($this->entries)];
					App::closeDB($db);
					
					return Visualizer::redirect("{$entry->subject}/{$entry->id}");
				}
			}
			
			$this->entryCount = null;
			$this->entries = ThreadEntry::getEntriesByName($db, $name, $page * Configuration::$instance->searchPaging, Configuration::$instance->searchPaging, Board::ORDER_DESCEND, $this->entryCount);

			$this->lastUpdate = max(array_map(fn($x) => $x->getLatestLastUpdate(), $this->entries) + array(0));
			$hash = implode(",", array_map(fn($x) => $x->id . ":" . (time() - $x->getLatestLastUpdate() < Configuration::$instance->updatePeriod * 24 * 60 * 60 ? "t" : "n"), $this->entries));
			
			if (Util::isCachedByBrowser($this->lastUpdate, Cookie::getCookie(Cookie::LIST_TYPE_KEY, "") . Cookie::getCookie(Cookie::LIST_VISIBILITY_KEY, "") . $hash))
				Visualizer::notModified();
			
			$pageCount = (int)ceil((is_null($this->entryCount) ? ThreadEntry::getEntryCountByName($db, $name) : $this->entryCount) / Configuration::$instance->searchPaging);
			$this->subject = 0;
			$this->subjectCount = Board::getLatestSubject($db);
			Visualizer::$data = $name;
		}
		
		if (!$isNameList && !Configuration::$instance->showName[Configuration::ON_SUBJECT])
		{
			$pageCount = 0;
			$this->entries = array();
		}

		App::closeDB($db);
		
		$this->page = $page + 1;
		$this->pageCount = $pageCount;
		
		switch (App::$handlerType)
		{
			case "json":
				if ($isNameList)
					return Visualizer::json(array
					(
						"names" => Visualizer::$data,
						"page" => $this->page,
						"pageCount" => $this->pageCount
					));
				else
					return Visualizer::json(array
					(
						"name" => $name,
						"entries" => array_values(array_map(fn($x) => $x->toArray(Configuration::ON_SUBJECT), $this->entries)),
						"page" => $this->page,
						"pageCount" => $this->pageCount
					));
			case "csv":
				return self::toCSV($this->entries);
			case "atom":
				if ($isNameList)
					throw new ApplicationException("ファイルが見つかりません", 404);
				else
					return Visualizer::visualize("Index/Index.Atom", 200, "application/atom+xml");
			case "rss":
				if ($isNameList)
					throw new ApplicationException("ファイルが見つかりません", 404);
				else
					return Visualizer::visualize("Index/Index.Rss", 200, "application/rss+xml");
			default:
				return Visualizer::visualize($isNameList ? "Index/Tag" : "Index/Index");
		}
	}
	
	function tag(): bool
	{
		$args = func_get_args();
		$page = $args && ctype_digit($args[count($args) - 1]) ? max(intval(array_pop($args)) - 1, 0) : 0;
		$isTagList = !$args;
		$tag = $isTagList ? null : Util::escapeInput(implode("/", $args));
		
		if (!Auth::hasSession(true) && !Configuration::$instance->showTitle[Configuration::ON_SUBJECT])
			throw new ApplicationException("作品の閲覧は許可されていません", 403);
		
		$db = App::openDB();
		$this->entries = array();

		if ($isTagList || !isset($tag))
		{
			$pageCount = ceil(($this->entryCount = ThreadEntry::getTagCount($db)) / Configuration::$instance->tagListing);
			Visualizer::$data = ThreadEntry::getTags($db, $page * Configuration::$instance->tagListing, Configuration::$instance->tagListing);
		}
		else
		{
			if ($tag == "random")
			{
				$arr = ThreadEntry::getTags($db);
				App::closeDB($db);
				
				return Visualizer::redirect("tag" . ($arr ? "/" . array_rand($arr) : ""));
			}
			
			if (strstr($tag, "/random") == "/random")
			{
				$tag = substr($tag, 0, -strlen("/random"));
				$this->entries = ThreadEntry::getEntriesByTag($db, $tag);
				
				if ($this->entries)
				{
					$entry = $this->entries[array_rand($this->entries)];
					App::closeDB($db);
					
					return Visualizer::redirect("{$entry->subject}/{$entry->id}");
				}
			}
			
			$this->entryCount = null;
			$this->entries = ThreadEntry::getEntriesByTag($db, $tag, $page * Configuration::$instance->searchPaging, Configuration::$instance->searchPaging, Board::ORDER_DESCEND, $this->entryCount);
			
			$this->lastUpdate = max(array_map(fn($x) => $x->getLatestLastUpdate(), $this->entries) + array(0));
			$hash = implode(",", array_map(fn($x) => $x->id . ":" . (time() - $x->getLatestLastUpdate() < Configuration::$instance->updatePeriod * 24 * 60 * 60 ? "t" : "n"), $this->entries));
			
			if (Util::isCachedByBrowser($this->lastUpdate, Cookie::getCookie(Cookie::LIST_TYPE_KEY, "") . Cookie::getCookie(Cookie::LIST_VISIBILITY_KEY, "") . $hash))
				Visualizer::notModified();
			
			$pageCount = ceil((is_null($this->entryCount) ? ThreadEntry::getEntryCountByTag($db, $tag) : $this->entryCount) / Configuration::$instance->searchPaging);
			$this->subject = 0;
			$this->subjectCount = Board::getLatestSubject($db);
			Visualizer::$data = $tag;
		}
		
		if (!$isTagList && !Configuration::$instance->showTags[Configuration::ON_SUBJECT])
		{
			$pageCount = 0;
			$this->entries = array();
		}
		
		App::closeDB($db);
		
		$this->page = $page + 1;
		$this->pageCount = (int)$pageCount;
		
		switch (App::$handlerType)
		{
			case "json":
				if ($isTagList)
					return Visualizer::json(array
					(
						"tags" => Visualizer::$data,
						"page" => $this->page,
						"pageCount" => $this->pageCount
					));
				else
					return Visualizer::json(array
					(
						"tag" => $tag,
						"entries" => array_values(array_map(fn($x) => $x->toArray(Configuration::ON_SUBJECT), $this->entries)),
						"page" => $this->page,
						"pageCount" => $this->pageCount
					));
			case "csv":
				return self::toCSV($this->entries);
			case "atom":
				if ($isTagList)
					throw new ApplicationException("ファイルが見つかりません", 404);
				else
					return Visualizer::visualize("Index/Index.Atom", 200, "application/atom+xml");
			case "rss":
				if ($isTagList)
					throw new ApplicationException("ファイルが見つかりません", 404);
				else
					return Visualizer::visualize("Index/Index.Rss", 200, "application/rss+xml");
			default:
				return Visualizer::visualize($isTagList ? "Index/Tag" : "Index/Index");
		}
	}

	private static function toCSV(array $entries): bool
	{
		$c = &Configuration::$instance;
		$visibility = array_filter(array
		(
			"id" => true,
			"subject" => true,
			"title" => $c->showTitle[Configuration::ON_SUBJECT],
			"name" => $c->showName[Configuration::ON_SUBJECT],
			"dateTime" => true,
			"lastUpdate" => true,
			"pageCount" => $c->showPages[Configuration::ON_SUBJECT],
			"size" => $c->showSize[Configuration::ON_SUBJECT],
			"points" => $c->showPoint[Configuration::ON_SUBJECT],
			"responseCount" => $c->showComment[Configuration::ON_SUBJECT],
			"commentCount" => $c->showComment[Configuration::ON_SUBJECT],
			"evaluationCount" => $c->showPoint[Configuration::ON_SUBJECT],
			"readCount" => $c->showReadCount[Configuration::ON_SUBJECT],
		));
		$rt = array();
		
		foreach ($entries as $i)
		{
			$arr = $i->toArray(Configuration::ON_SUBJECT);
			$arr["dateTime"] = Visualizer::formatDateTime($arr["dateTime"]);
			$arr["lastUpdate"] = Visualizer::formatDateTime($arr["lastUpdate"]);
			$rt[] = array_intersect_key($arr, $visibility);
		}
		
		return Visualizer::csv(array_merge
		(
			array(array_keys($visibility)),
			$rt
		));
	}

	function util(): bool
	{
		$args = func_get_args();
		
		return App::callHandler("util", $args ? App::$actionName = array_shift($args) : App::$actionName = "index", $args);
	}
	
	function login(): bool
	{
		Auth::$caption = "管理者ログイン";
		
		if (($password = Auth::login(true)) && !Util::hashEquals(Configuration::$instance->adminHash ?? "", $password))
			Auth::loginError("管理者パスワードが一致しません");
		else
			return Visualizer::redirect(isset($_GET["redir"]) && is_string($_GET["redir"]) ? $_GET["redir"] : "");
	}
	
	function logout(): bool
	{
		Auth::logout();
		
		return Visualizer::redirect(isset($_GET["redir"]) && is_string($_GET["redir"]) ? $_GET["redir"] : "");
	}
	
	function notice(string $_name = ""): bool
	{
		$name = Util::escapeInput($_name);
		Visualizer::$data = Constant::DATA_DIR . "notice/{$name}.txt";
		
		if (is_file(Visualizer::$data))
			return Visualizer::visualize();
		else
			throw new ApplicationException("ファイルが見つかりません", 404);
	}
	
	// Megalith compatibility layer
	function sub(): bool
	{
		$args = func_get_args();
		
		return App::callHandler("Megalith", "sub", $args);
	}
	function dat(): bool
	{
		$args = func_get_args();
		
		return App::callHandler("Megalith", "dat", $args);
	}
	function _com(): bool
	{
		$args = func_get_args();
		
		return App::callHandler("Megalith", "_com", $args);
	}
	function aft(): bool
	{
		$args = func_get_args();
		
		return App::callHandler("Megalith", "aft", $args);
	}
	function settings(): bool
	{
		$args = func_get_args();
		
		return App::callHandler("Megalith", "settings", $args);
	}
}
?>
