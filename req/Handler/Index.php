<?php
class IndexHandler extends Handler
{
	/**
	 * @var IndexHandler
	 */
	static $instance;
	
	public $subject;
	public $subjectCount;
	public $page;
	public $pageCount;
	public $entries;
	
	function index($_subject = "0", $_id = 0)
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
		
		if (isset($_POST["admin"]))
		{
			Auth::ensureToken();
			Auth::createToken();
			
			if (!Util::hashEquals(Configuration::$instance->adminHash, Auth::login(true)))
				Auth::loginError("管理者パスワードが一致しません");
			
			$ids = array_map("intval", array_map(array("Util", "escapeInput"), isset($_POST["id"]) ? (is_array($_POST["id"]) ? $_POST["id"] : array($_POST["id"])) : array()));
			$threads = $ids ? array_filter(array_map(array("Thread", "load"), array_fill(0, count($ids), $db), $ids)) : array();
			
			switch ($mode = Util::escapeInput($_POST["admin"]))
			{
				case "unpost":
					foreach ($threads as $i)
						$i->delete($db);
					
					break;
			}
		}
		
		if ($subject == 0)
			$subject = Board::getLatestSubject($db);
		else if ($subject > Board::getLatestSubject($db))
			throw new ApplicationException("指定された番号 {$subject} の作品集は存在しません", 404);
		
		$this->entries = ThreadEntry::getEntriesBySubject($db, $subject);
		$this->subject = $subject;
		$this->subjectCount = Board::getSubjectCount($db);
		
		App::closeDB($db);
		
		switch (App::$handlerType)
		{
			case "json":
				return Visualizer::json(array
				(
					"entries" => array_map(create_function('$_', 'return $_->toArray();'), $this->entries),
					"subject" => $this->subject,
					"subjectCount" => $this->subjectCount
				));
			case "atom":
				return Visualizer::visualize("Index/Index.Atom", 200, "application/atom+xml");
			case "rss":
				return Visualizer::visualize("Index/Index.Rss", 200, "application/rss+xml");
			default:
				return Visualizer::visualize();
		}
	}
	
	function search()
	{
		if (!Configuration::$instance->showTitle[Configuration::ON_SUBJECT])
			throw new ApplicationException("作品の閲覧は許可されていません", 403);
		
		$this->page = max(intval(self::param("p", 1)), 1);
		
		switch ($s = self::param("s"))
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
		
		$db = App::openDB();
		$idb = App::openDB(App::INDEX_DATABASE);
		$vals = ThreadEntry::getMaxMinValues($db);
		$query = array
		(
			"query" => Util::splitTags(self::param("query")),
			"title" => Util::splitTags(self::param("title")),
			"name" => Util::splitTags(self::param("name")),
			"tag" => Util::splitTags(self::param("tags")),
			"eval" => array
			(
				!Util::isEmpty(self::param("evalBegin")) ? intval(self::param("evalBegin")) : $vals["minEval"],
				!Util::isEmpty(self::param("evalEnd")) ? intval(self::param("evalEnd")) : $vals["maxEval"]
			),
			"points" => array
			(
				!Util::isEmpty(self::param("pointsBegin")) ? intval(self::param("pointsBegin")) : $vals["minPoints"],
				!Util::isEmpty(self::param("pointsBegin")) ? intval(self::param("pointsEnd")) : $vals["maxPoints"]
			),
			"dateTime" => array
			(
				self::param("dateTimeBegin"),
				self::param("dateTimeEnd")
			)
		);
		
		if (substr_count($query["dateTime"][0], "-") == 2)
		{
			$d = explode("-", $query["dateTime"][0]);
			$query["dateTime"][0] = mktime(0, 0, 0, intval($d[1]), intval($d[2]), intval($d[0]));
		}
		else
			$query["dateTime"][0] = $vals["minDateTime"];
		
		if (substr_count($query["dateTime"][1], "-") == 2)
		{
			$d = explode("-", $query["dateTime"][1]);
			$query["dateTime"][1] = mktime(23, 59, 59, intval($d[1]), intval($d[2]), intval($d[0]));
		}
		else
			$query["dateTime"][1] = $vals["maxDateTime"];
		
		if ($query["query"] ||
			$query["title"] ||
			$query["name"] ||
			$query["tag"] ||
			$query["eval"][0] != $vals["minEval"] ||
			$query["eval"][1] != $vals["maxEval"] ||
			$query["points"][0] != $vals["minPoints"] ||
			$query["points"][1] != $vals["maxPoints"] ||
			date("Y-m-d", $query["dateTime"][0]) != date("Y-m-d", $vals["minDateTime"]) ||
			date("Y-m-d", $query["dateTime"][1]) != date("Y-m-d", $vals["maxDateTime"]))
		{
			if (self::param("random") == "true")
			{
				$rt = ThreadEntry::search($db, $idb, $query, 0, 0, ThreadEntry::SEARCH_RANDOM);
				
				return Visualizer::redirect("{$rt->subject}/{$rt->id}");
			}
			
			$rt = ThreadEntry::search($db, $idb, $query, ($this->page - 1) * Configuration::$instance->searchPaging, Configuration::$instance->searchPaging, $sort[0], $sort[1]);
			$this->pageCount = ceil($rt["count"] / Configuration::$instance->searchPaging);
			$this->entries = $rt["result"];
		}
		
		if (!Auth::hasToken())
			Auth::createToken();
		
		if (isset($_POST["admin"]) && $this->entries)
		{
			Auth::ensureToken();
			Auth::createToken();
			
			if (!Util::hashEquals(Configuration::$instance->adminHash, Auth::login(true)))
				Auth::loginError("管理者パスワードが一致しません");
			
			$this->entries = array_combine(array_map(create_function('$_', 'return $_->id;'), $this->entries), $this->entries);
			$ids = array_map("intval", array_map(array("Util", "escapeInput"), isset($_POST["id"]) ? (is_array($_POST["id"]) ? $_POST["id"] : array($_POST["id"])) : array()));
			$threads = array();
			
			foreach ($ids as $i)
				if (isset($this->entries[$i]))
					$threads[] = $this->entries[$i];
			
			switch ($mode = Util::escapeInput($_POST["admin"]))
			{
				case "unpost":
					foreach ($threads as $i)
					{
						$i->delete($db);
						unset($this->entries[$i->id]);
						$rt["count"]--;
					}
					
					break;
			}
		}
		
		App::closeDB($idb);
		App::closeDB($db);
		Visualizer::$data = array
		(
			"count" => isset($rt) ? $rt["count"] : 0,
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
			"dateTimeMin" => date("Y-m-d", $vals["minDateTime"]),
			"dateTimeMax" => date("Y-m-d", $vals["maxDateTime"])
		);
		
		switch (App::$handlerType)
		{
			case "json":
				return Visualizer::json(array
				(
					"entries" => array_map(create_function('$_', 'return $_->toArray();'), $this->entries),
					"page" => $this->page,
					"pageCount" => $this->pageCount
				));
			default:
				return Visualizer::visualize();
		}
	}
	
	static function param($name, $value = null)
	{
		if (isset($_GET[$name]))
			return Util::escapeInput($_GET[$name]);
		else
			return $value;
	}
	
	function _new()
	{
		$args = func_get_args();
		
		return App::callHandler("read", "_new", $args);
	}
	
	function edit()
	{
		$args = func_get_args();
		
		return App::callHandler("read", "edit", $args);
	}
	
	function post()
	{
		$args = func_get_args();
		
		return App::callHandler("read", "post", $args);
	}
	
	function unpost()
	{
		$args = func_get_args();
		
		return App::callHandler("read", "unpost", $args);
	}
	
	function comment()
	{
		$args = func_get_args();
		
		return App::callHandler("read", "comment", $args);
	}
	
	function uncomment()
	{
		$args = func_get_args();
		
		return App::callHandler("read", "uncomment", $args);
	}
	
	function evaluate()
	{
		$args = func_get_args();
		
		return App::callHandler("read", "evaluate", $args);
	}
	
	function unevaluate()
	{
		$args = func_get_args();
		
		return App::callHandler("read", "unevaluate", $args);
	}
	
	function config()
	{
		$args = func_get_args();
		
		return App::callHandler("util", "config", $args);
	}
	
	function random()
	{
		$db = App::openDB();
		$entry = ThreadEntry::getRandomEntry($db);
		App::closeDB($db);
		
		if ($entry)
			return Visualizer::redirect("{$entry->subject}/{$entry->id}");
		else
			return Visualizer::redirect();
	}
	
	function author($_name = null, $_page = 1)
	{
		$isNameList = is_null($_name);
		$name = Util::escapeInput($_name);
		$page = max(intval($isNameList ? $_name : $_page) - 1, 0);
		
		$db = App::openDB();
		
		if ($isNameList)
		{
			$pageCount = ceil(ThreadEntry::getNameCount($db) / Configuration::$instance->tagListing);
			Visualizer::$data = ThreadEntry::getNames($db, $page * Configuration::$instance->tagListing, Configuration::$instance->tagListing);
		}
		else
		{
			$pageCount = ceil(ThreadEntry::getEntryCountByName($db, $name) / Configuration::$instance->searchPaging);
			$this->entries = ThreadEntry::getEntriesByName($db, $name, $page * Configuration::$instance->searchPaging, Configuration::$instance->searchPaging);
			$this->subject = 0;
			$this->subjectCount = Board::getSubjectCount($db);
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
						"entries" => array_map(create_function('$_', 'return $_->toArray();'), $this->entries),
						"page" => $this->page,
						"pageCount" => $this->pageCount
					));
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
	
	function tag($_tag = null, $_page = 1)
	{
		$isTagList = is_null($_tag) || intval($_tag) > 0;
		$tag = $isTagList ? null : Util::escapeInput($_tag);
		$page = max(intval($isTagList ? $_tag : $_page) - 1, 0);
		
		$db = App::openDB();
		
		if ($isTagList)
		{
			$pageCount = ceil(ThreadEntry::getTagCount($db) / Configuration::$instance->tagListing);
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
			
			$pageCount = ceil(ThreadEntry::getEntryCountByTag($db, $tag) / Configuration::$instance->searchPaging);
			$this->entries = ThreadEntry::getEntriesByTag($db, $tag, $page * Configuration::$instance->searchPaging, Configuration::$instance->searchPaging);
			$this->subject = 0;
			$this->subjectCount = Board::getSubjectCount($db);
			Visualizer::$data = $tag;
		}
		
		if (!$isTagList && !Configuration::$instance->showTags[Configuration::ON_SUBJECT])
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
						"entries" => array_map(create_function('$_', 'return $_->toArray();'), $this->entries),
						"page" => $this->page,
						"pageCount" => $this->pageCount
					));
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

	function util()
	{
		$args = func_get_args();
		
		return App::callHandler("util", $args ? App::$actionName = array_shift($args) : App::$actionName = "index", $args);
	}
	
	function login()
	{
		Auth::$caption = "管理者ログイン";
		
		if (!Util::hashEquals(Configuration::$instance->adminHash, Auth::login(true)))
			Auth::loginError("管理者パスワードが一致しません");
		else
			return Visualizer::redirect();
	}
	
	function logout()
	{
		Auth::logout();
		
		return Visualizer::redirect();
	}
	
	function manifest()
	{
		header("Content-Type: text/cache-manifest");
		
		?>
CACHE MANIFEST
# rev 0
		<?php
		echo implode("\r\n", array
		(
			"http://code.jquery.com/",
			"http://nehan.googlecode.com/",
			"style/",
			"script/"
		));
		?>
NETWORK:
*
		<?php
		echo Util::getAbsoluteUrl();
		
		return null;
	}
	
	function notice($_name = "")
	{
		$name = Util::escapeInput($_name);
		Visualizer::$data = DATA_DIR . "notice/{$name}.txt";
		
		if (is_file(Visualizer::$data))
			return Visualizer::visualize();
		else
			throw new ApplicationException("ファイルが見つかりません", 404);
	}
	
	// Megalith compatibility layer
	function sub()
	{
		$args = func_get_args();
		
		return App::callHandler("Megalith", "sub", $args);
	}
	function dat()
	{
		$args = func_get_args();
		
		return App::callHandler("Megalith", "dat", $args);
	}
	function _com()
	{
		$args = func_get_args();
		
		return App::callHandler("Megalith", "_com", $args);
	}
	function aft()
	{
		$args = func_get_args();
		
		return App::callHandler("Megalith", "aft", $args);
	}
	function settings()
	{
		$args = func_get_args();
		
		return App::callHandler("Megalith", "settings", $args);
	}
}
?>