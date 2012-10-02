<?php
class UtilHandler extends Handler
{
	/**
	 * @var UtilHandler
	 */
	static $instance;
	
	function index()
	{
		Auth::$caption = "管理者ログイン";
		
		if (!Configuration::$instance->utilsEnabled && !Util::hashEquals(Configuration::$instance->adminHash, Auth::login(true)))
			Auth::loginError("管理者パスワードが一致しません");
		
		return Visualizer::visualize();
	}
	
	function track()
	{
		Auth::$caption = "管理者ログイン";
		
		if (!Util::hashEquals(Configuration::$instance->adminHash, Auth::login(true)))
			Auth::loginError("管理者パスワードが一致しません");
		
		Auth::cleanSession(!Auth::hasSession(true));
		
		if (!Auth::hasToken())
			Auth::createToken();
		
		$db = App::openDB();
		$subjectCount = Board::getLatestSubject($db);
		$subjectBegin = max(1, min(IndexHandler::param("subjectBegin", $subjectCount), $subjectCount));
		$subjectEnd = max(1, min(IndexHandler::param("subjectEnd", $subjectCount), $subjectCount));
		list($subjectBegin, $subjectEnd) = array(min($subjectBegin, $subjectEnd), max($subjectBegin, $subjectEnd));
		Visualizer::$data = array
		(
			"host" => IndexHandler::param("host"),
			"subjectCount" => $subjectCount,
			"subjectBegin" => $subjectBegin,
			"subjectEnd" => $subjectEnd,
			"target" => IndexHandler::param("target", "thread,evaluation,comment"),
			"entries" => null,
			"page" => intval(IndexHandler::param("p", 1)),
			"pageCount" => 0,
			"count" => 0,
		);
		
		if (!is_array(Visualizer::$data["target"]))
			Visualizer::$data["target"] = explode(",", Visualizer::$data["target"]);
		
		if (isset($_GET["host"]))
		{
			Visualizer::$data["entries"] = ThreadEntry::getEntriesByHost
			(
				$db,
				Visualizer::$data["host"],
				array($subjectBegin, $subjectEnd),
				Visualizer::$data["target"],
				(Visualizer::$data["page"] - 1) * Configuration::$instance->searchPaging,
				Configuration::$instance->searchPaging,
				Board::ORDER_DESCEND,
				Visualizer::$data["count"]
			);
			
			Visualizer::$data["pageCount"] = ceil(Visualizer::$data["count"] / Configuration::$instance->searchPaging);
			
			if (isset($_POST["admin"]))
			{
				Auth::ensureToken();
				Auth::createToken();
				
				$idb = App::openDB(App::INDEX_DATABASE);
				
				if (!Util::hashEquals(Configuration::$instance->adminHash, Auth::login(true)))
					Auth::loginError("管理者パスワードが一致しません");
				
				$ids = array_map("intval", array_map(array("Util", "escapeInput"), isset($_POST["id"]) ? (is_array($_POST["id"]) ? $_POST["id"] : array($_POST["id"])) : array()));
				$db->beginTransaction();
				
				if ($db !== $idb)
					$idb->beginTransaction();
				
				switch ($mode = Util::escapeInput($_POST["admin"]))
				{
					case "unpost":
						ThreadEntry::deleteDirect($db, $idb, $ids);
					
						foreach (array_unique(array_map(create_function('$_', 'return $_->subject;'), array_intersect_key(Visualizer::$data["entries"], array_flip($ids)))) as $i)
							Board::setLastUpdate($db, $i);
						
						foreach ($ids as $i)
							unset(Visualizer::$data["entries"][$i]);
						
						Visualizer::$data["count"] -= count($ids);
						
						break;
				}
				
				if ($db !== $idb)
					$idb->commit();
				
				$db->commit();
				
				App::closeDB($idb);
			}
		}
		
		App::closeDB($db);
		
		return Visualizer::visualize();
	}
	
	function hash()
	{
		self::ensureTestMode(false);
		
		if (isset($_POST["raw"]))
		{
			$raw = Util::escapeInput($_POST["raw"]);
			
			Visualizer::$data = array
			(
				"raw" => $raw,
				"hash" => Util::hash($raw)
			);
			
			assert('Util::hashEquals(Util::hash($raw), $raw)');
		}
		
		return Visualizer::visualize();
	}
	
	function reindex()
	{
		$defaultBuffer = 40;
		$minimumBuffer = 5;
		
		self::ensureTestMode();
		
		if (isset($_GET["p"]))
		{
			$param = Util::escapeInput($_GET["p"]);
			
			if ($param == "list")
			{
				$db = App::openDB();
				$idb = App::openDB(App::INDEX_DATABASE);
				$maxSubject = Board::getLatestSubject($db);
				
				if (isset($_GET["force"]) && $_GET["force"] == "yes")
					SearchIndex::clear($idb);
				
				App::closeDB($idb);
				App::closeDB($db);
				
				if (App::$handlerType == "json")
					return Visualizer::json(array
					(
						"remainingChildren" => 1,
						"allChildren" => 1,
						"nextOffset" => 0,
						"current" => 0,
						"next" => 1,
						"max" => $maxSubject,
						"count" => 0,
						"buffer" => $defaultBuffer
					));
				else
					return Visualizer::redirect("util/reindex?p=1");
			}
			else if ($param == "end")
			{
				Visualizer::$data = isset($_GET["c"]) ? intval($_GET["c"]) : 0;
				
				if (App::$handlerType == "json")
					return Visualizer::json(array
					(
						"count" => Visualizer::$data
					));
				else
					return Visualizer::visualize();
			}
			else
			{
				$current = intval($param);
				$count = isset($_GET["c"]) ? intval($_GET["c"]) : 0;
				$offset = isset($_GET["o"]) ? intval($_GET["o"]) : 0;
				$buffer = isset($_GET["b"]) ? intval($_GET["b"]) : $defaultBuffer;
				
				$db = App::openDB();
				$idb = App::openDB(App::INDEX_DATABASE);
				$maxSubject = Board::getLatestSubject($db);
				
				$idb->beginTransaction();
				
				$rt = SearchIndex::$instance->registerSubject($db, $idb, $current, $offset, $buffer);
				$count += $rt[0];
				$nextOffset = $rt[1] <= 0 ? 0 : $offset += $buffer;
				$next = $nextOffset == 0 ? $current + 1 : $current;
				
				$idb->commit();
				
				App::closeDB($idb);
				App::closeDB($db);
				
				if (App::$handlerType == "json")
					return Visualizer::json(array
					(
						"remainingChildren" => $rt[1],
						"allChildren" => $rt[2],
						"nextOffset" => $nextOffset,
						"current" => $current,
						"next" => $next > $maxSubject ? null : $next,
						"max" => $maxSubject,
						"count" => $count,
						"buffer" => max($buffer, $minimumBuffer)
					));
				else
					return Visualizer::redirect($next > $maxSubject ? "util/reindex?p=end&c={$count}" : "util/reindex?p={$next}&c={$count}&o={$nextOffset}");
			}
		}
		else
			return Visualizer::visualize();
	}
	
	function convert()
	{
		$args = func_get_args();
		
		if ($args && $args[0] == "tags")
			return $this->convertTags(App::$actionName = array_shift($args), $args);
		
		$defaultBuffer = Configuration::$instance->convertDivision;
		$minimumBuffer = 20;
		
		self::ensureTestMode();
		
		$dir = "Megalith/";
		
		if (!is_dir("{$dir}")) throw new ApplicationException("ディレクトリ {$dir} が見つかりません");
		if (is_dir("{$dir}sub") && (!is_dir("{$dir}dat") || !is_dir("{$dir}com") || !is_dir("{$dir}aft"))) throw new ApplicationException("ディレクトリ {$dir}sub/ が見つかりましたが、他のログディレクトリが見つかりません");
		if (is_dir("{$dir}dat") && (!is_dir("{$dir}sub") || !is_dir("{$dir}com") || !is_dir("{$dir}aft"))) throw new ApplicationException("ディレクトリ {$dir}dat/ が見つかりましたが、他のログディレクトリが見つかりません");
		if (is_dir("{$dir}com") && (!is_dir("{$dir}sub") || !is_dir("{$dir}dat") || !is_dir("{$dir}aft"))) throw new ApplicationException("ディレクトリ {$dir}com/ が見つかりましたが、他のログディレクトリが見つかりません");
		if (is_dir("{$dir}aft") && (!is_dir("{$dir}sub") || !is_dir("{$dir}dat") || !is_dir("{$dir}com"))) throw new ApplicationException("ディレクトリ {$dir}aft/ が見つかりましたが、他のログディレクトリが見つかりません");
		
		if (isset($_GET["p"]))
		{
			$params = explode(",", Util::escapeInput($_GET["p"]));
			$db = App::openDB();
			$idb = App::openDB(App::INDEX_DATABASE);
			$allowOverwrite = isset($_GET["allowOverwrite"]) && $_GET["allowOverwrite"] == "yes";
			$whenNoConvertLineBreakFieldOnly = isset($_GET["whenNoConvertLineBreakFieldOnly"]) && $_GET["whenNoConvertLineBreakFieldOnly"] == "yes";
			$whenContainsWin31JOnly = isset($_GET["whenContainsWin31JOnly"]) && $_GET["whenContainsWin31JOnly"] == "yes";
			
			if ($params[0] == "list")
			{
				$subjects = array_merge(array_slice(Util::readLines("{$dir}sub/subjects.txt"), 1), array("subject.txt"));
				$subjectNum = 0;
				$subjectRange = array();
				
				foreach ($subjects as $k => $v)
				{
					$subjectNum = $k;
					$v = trim($v);
					
					if (!is_file($subjectFile = "{$dir}sub/{$v}"))
						continue;
					
					$previousSubjectFile = "{$dir}sub/subject{$subjectNum}.txt";
					$nextSubjectFile = "{$dir}sub/subject" . ($subjectNum == count($subjects) - 2 ? "" : $subjectNum + 2) . ".txt";
					$stats = Util::readLines($subjectFile, FILE_SKIP_EMPTY_LINES);
					$count = count($stats);
					
					$set = array
					(
						"start" => $subjectNum > 0
							? (is_file($previousSubjectFile) ? max(self::getFirstAndLastDataLineIDFromLines(Util::readLines($previousSubjectFile, FILE_SKIP_EMPTY_LINES))) + 1 : min(self::getFirstAndLastDataLineIDFromLines($stats)))
							: 0,
						"end" => $subjectNum < count($subjects)
							? (is_file($nextSubjectFile) ? min(self::getFirstAndLastDataLineIDFromLines(Util::readLines($nextSubjectFile, FILE_SKIP_EMPTY_LINES))) : max(self::getFirstAndLastDataLineIDFromLines($stats)) + 1)
							: 0
					);
					$subjectRange[] = $set;
					
					unset($previousSubjectFile);
					unset($nextSubjectFile);
					unset($stats);
					unset($count);
					unset($set);
				}
				
				$subjectRange = array_map(create_function('$k, $v', 'return ($k + 1) . "-{$v[\'start\']}-{$v[\'end\']}";'), array_keys($subjectRange), array_values($subjectRange));
				$subjectRange[] = "end";
				
				if (App::$handlerType == "json")
					return Visualizer::json(array
					(
						"remaining" => $subjectRange,
						"count" => 0,
						"buffer" => $defaultBuffer,
						"allowOverwrite" => $allowOverwrite ? "yes" : "no",
						"whenNoConvertLineBreakFieldOnly" => $whenNoConvertLineBreakFieldOnly ? "yes" : "no",
						"whenContainsWin31JOnly" => $whenContainsWin31JOnly ? "yes" : "no",
					));
				else
					return Visualizer::redirect("util/convert?p=" . urlencode(implode(",", $subjectRange)));
			}
			else if ($params[0] == "end")
			{
				Visualizer::$data = isset($_GET["c"]) ? intval($_GET["c"]) : 0;
				
				if (App::$handlerType == "json")
					return Visualizer::json(array
					(
						"count" => Visualizer::$data
					));
				else
					return Visualizer::visualize();
			}
			else
			{
				$l = explode("-", array_shift($params));
				$subject = intval($l[0]);
				$start = intval($l[1]);
				$end = intval($l[2]);
				$count = isset($_GET["c"]) ? intval($_GET["c"]) : 0;
				$buffer = isset($_GET["b"]) ? intval($_GET["b"]) : $defaultBuffer;
				$currentCount = 0;
				$firstID = 0;
				$existing = ThreadEntry::getEntryIDsBySubject($db, $subject);
				
				$db->beginTransaction();
							
				if ($db !== $idb)
					$idb->beginTransaction();
				
				foreach (new DirectoryIterator("{$dir}dat") as $i)
					if ($i->isFile() &&
						mb_strstr($i->getFilename(), ".") == ".dat" &&
						($id = intval(mb_substr($i->getFilename(), 0, -4))) >= $start &&
						($end == 0 || $id < $end))
					{
						$datLines = is_file($dat = "{$dir}dat/{$id}.dat") ? array_map(create_function('$_', 'return mb_convert_encoding($_, "UTF-8", "Windows-31J");'), Util::readLines($dat)) : null;
						$entry = null;
						
						if ($datLines)
							$datLines[0] = "{$id}.dat<>{$datLines[0]}";
						
						if (in_array($id, $existing))
							if ($allowOverwrite && $datLines)
							{
								$converting = Util::convertLineToThreadEntry($datLines[0]);
								$entry = ThreadEntry::load($db, $id);
								
								if ($entry->lastUpdate > $converting->lastUpdate)
									continue;
								
								$converting->responseLastUpdate = $entry->responseLastUpdate;
								$entry = $converting;
							}
							else
								continue;
						
						if ($whenNoConvertLineBreakFieldOnly && $datLines && count(explode("<>", $datLines[0])) > 13)
							continue;
						
						if ($whenContainsWin31JOnly)
							unset($datLines);
						
						try
						{
							$thread = Util::convertAndSaveToThread
							(
								$db,
								$idb,
								$subject,
								$whenContainsWin31JOnly ? "{$dir}dat/{$id}.dat" : $datLines,
								"{$dir}com/{$id}.res.dat",
								"{$dir}aft/{$id}.aft.dat",
								$whenContainsWin31JOnly,
								$whenContainsWin31JOnly && $allowOverwrite,
								$entry
							);
						}
						catch (ApplicationException $ex)
						{
							$ex->data = array
							(
								"id" => $id,
								"subject" => $subject,
							);
							
							App::closeDB($db);
							App::closeDB($idb);
							
							$db = App::openDB();
							$idb = App::openDB(App::INDEX_DATABASE);
							$db->beginTransaction();
							
							if ($db !== $idb)
								$idb->beginTransaction();
							
							ThreadEntry::deleteDirect($db, $idb, array($id));
							
							if ($db !== $idb)
								$idb->commit();
							
							$db->commit();
							App::closeDB($idb);
							App::closeDB($db);
							
							throw $ex;
						}
						
						if (!$thread)
							continue;
						
						if ($firstID == 0)
							$firstID = $thread->id;
						
						$count++;
						
						if (++$currentCount == max($buffer, 1))
						{
							$lastID = $thread->id + 1;
							array_unshift($params, "{$subject}-{$lastID}-{$end}");
							
							break;
						}
					}
					
				if ($db !== $idb)
					$idb->commit();
				
				$db->commit();
				
				App::closeDB($idb);
				App::closeDB($db);
				
				if (App::$handlerType == "json")
					return Visualizer::json(array
					(
						"first" => $firstID,
						"remaining" => $params,
						"count" => $count,
						"buffer" => max($buffer, $minimumBuffer),
						"allowOverwrite" => $allowOverwrite ? "yes" : "no",
						"whenNoConvertLineBreakFieldOnly" => $whenNoConvertLineBreakFieldOnly ? "yes" : "no",
						"whenContainsWin31JOnly" => $whenContainsWin31JOnly ? "yes" : "no"
					));
				else
					return Visualizer::redirect("util/convert?p=" . urlencode(implode(",", $params)) . "&c={$count}");
			}
		}
		else
			return Visualizer::visualize();
	}
	
	static function convertTags()
	{
		$defaultBuffer = 1000;
		$minimumBuffer = 100;
		
		self::ensureTestMode();
		
		$dir = "Megalith/";
		
		if (!is_dir("{$dir}")) throw new ApplicationException("ディレクトリ {$dir} が見つかりません");
		if (is_dir("{$dir}sub") && (!is_dir("{$dir}dat") || !is_dir("{$dir}com") || !is_dir("{$dir}aft"))) throw new ApplicationException("ディレクトリ {$dir}sub/ が見つかりましたが、他のログディレクトリが見つかりません");
		if (is_dir("{$dir}dat") && (!is_dir("{$dir}sub") || !is_dir("{$dir}com") || !is_dir("{$dir}aft"))) throw new ApplicationException("ディレクトリ {$dir}dat/ が見つかりましたが、他のログディレクトリが見つかりません");
		if (is_dir("{$dir}com") && (!is_dir("{$dir}sub") || !is_dir("{$dir}dat") || !is_dir("{$dir}aft"))) throw new ApplicationException("ディレクトリ {$dir}com/ が見つかりましたが、他のログディレクトリが見つかりません");
		if (is_dir("{$dir}aft") && (!is_dir("{$dir}sub") || !is_dir("{$dir}dat") || !is_dir("{$dir}com"))) throw new ApplicationException("ディレクトリ {$dir}aft/ が見つかりましたが、他のログディレクトリが見つかりません");
		
		if (isset($_GET["p"]) && $_GET["p"] == "list")
		{
			$db = App::openDB();
			$subjectCount = Board::getLatestSubject($db);
			App::closeDB($db);
			
			if (App::$handlerType == "json")
				return Visualizer::json(array
				(
					"remainingChildren" => 1,
					"allChildren" => 1,
					"nextOffset" => 0,
					"current" => 0,
					"next" => 1,
					"max" => $subjectCount,
					"count" => 0,
					"buffer" => $defaultBuffer
				));
			else
				return Visualizer::redirect("util/convert/tags?s=1&o=0&m={$subjectCount}&c=0");
		}
		else if (isset($_GET["p"]) && $_GET["p"] == "end")
		{
			Visualizer::$data = isset($_GET["c"]) ? intval($_GET["c"]) : 0;
			
			if (App::$handlerType == "json")
				return Visualizer::json(array
				(
					"count" => Visualizer::$data
				));
			else
				return Visualizer::visualize("Util/Convert/Tags");
		}
		else if (isset($_GET["s"]) && isset($_GET["o"]) && isset($_GET["m"]))
		{
			$offset = intval(Util::escapeInput($_GET["o"]));
			$subjectCount = intval(Util::escapeInput($_GET["m"]));
			$count = intval(Util::escapeInput($_GET["c"]));
			$buffer = isset($_GET["b"]) ? intval($_GET["b"]) : $defaultBuffer;
			$db = App::openDB();
			$datCount = 0;
			$processed = 0;
			$next = 0;
			
			for ($subject = intval(Util::escapeInput($_GET["s"])); $subject < $subjectCount; $subject++)
			{
				$subjectFile = $dir . "sub/subject" . ($subject == $subjectCount ? "" : $subject) . ".txt";
				$datCount = 0;
				
				if (is_file($subjectFile))
				{
					$sub = array_map(create_function('$_', 'return mb_convert_encoding($_, "UTF-8", "Windows-31J");'), Util::readLines($subjectFile));
					$datCount = count($sub);
					
					foreach (array_slice($sub, $offset, $buffer) as $i)
					{
						$newEntry = Util::convertLineToThreadEntry($i);
						
						if ($newEntry && $newEntry->tags)
						{
							foreach ($newEntry->tags as $k => $v)
							{
								$st = Util::ensureStatement($db, $db->prepare(sprintf
								('
									update %s set position = :position where id = :id and tag = :tag',
									App::THREAD_TAG_TABLE
								)));
								$st->bindParam("id", $newEntry->id, PDO::PARAM_INT);
								$st->bindParam("tag", $v);
								$st->bindParam("position", $k, PDO::PARAM_INT);
								Util::executeStatement($st);
							}
							
							$count++;
						}
						
						$processed++;
						$offset++;
						
						if ($processed >= $buffer)
							break;
					}
				}
				
				$next = $subject;
				
				if ($offset >= $datCount)
				{
					$offset = 0;
					$next++;
				}
				
				if ($processed >= $buffer)
					break;
			}
				
			App::closeDB($db);
			
			if (App::$handlerType == "json")
				return Visualizer::json(array
				(
					"remainingChildren" => $datCount - $offset,
					"allChildren" => $datCount,
					"nextOffset" => $offset,
					"current" => $subject,
					"next" => $next > $subjectCount ? null : $next,
					"max" => $subjectCount,
					"count" => $count,
					"buffer" => max($buffer, $minimumBuffer)
				));
			else
				return Visualizer::redirect($next > $subjectCount ? "util/convert/tags?p=end&c={$count}" : "util/convert/tags?s={$next}&o={$offset}&m={$subjectCount}&c={$count}");
		}
		else
			return Visualizer::visualize("Util/Convert/Tags");
	}
	
	private static function getFirstAndLastDataLineIDFromLines(array $lines)
	{
		return array(self::getDataLineID($lines[0]), self::getDataLineID($lines[count($lines) - 1]));
	}
	
	private static function getDataLineID($s)
	{
		return intval(mb_substr($s, 0, mb_strpos($s, ".")));
	}
	
	function config()
	{
		if (Util::isCachedByBrowser(filemtime("config.php")))
			return Visualizer::notModified();
		
		$c = Configuration::$instance;
		$isAdmin = Auth::hasSession(true);
		$idb = App::openDB(App::INDEX_DATABASE);
		Visualizer::$data = array
		(
			"system" => array
			(
				lcfirst(App::NAME) => App::VERSION,
				"megalith" => App::MEGALITH_VERSION,
				"php" => phpversion(),
			) + ($isAdmin ? array
			(
				"pdoServer" => $idb->getAttribute(PDO::ATTR_SERVER_VERSION),
				"pdoClient" => $idb->getAttribute(PDO::ATTR_CLIENT_VERSION),
				"pdoDriver" => $idb->getAttribute(PDO::ATTR_DRIVER_NAME),
				"currentSearch" => SearchIndex::isUpgradeRequired($idb) ? "classic" : strtolower(SearchIndex::getAvailableType()),
				"availableSearch" => strtolower(SearchIndex::getAvailableType()),
			) : array()),
			"configuration" => array
			(
				"title" =>
					array("タイトル", $c->title),
				"bbq" =>
					array("BBQ 適用先", implode("", array_slice(array("none", "read", "write", "read, write"), $c->useBBQ, 1))),
				"pointEnabled" =>
					array("簡易評価可否", $c->usePoints()),
				"pointMap" =>
					array("簡易評価点数表", $c->pointMap),
				"commentEnabled" =>
					array("コメント可否", $c->useComments),
				"commentPointEnabled" =>
					array("コメント評価可否", $c->useCommentPoints()),
				"commentPointMap" =>
					array("コメント評価点数表", $c->commentPointMap),
				"adminOnly" =>
					array("管理者のみ投稿可", $c->adminOnly),
				"defaultName" =>
					array("既定の名前", $c->defaultName),
				"requireNameOnEntry" =>
					array("作品投稿時名前必須", $c->requireName[Configuration::ON_ENTRY]),
				"requireNameOnComment" =>
					array("コメント時名前必須", $c->requireName[Configuration::ON_COMMENT]),
				"requirePasswordOnEntry" =>
					array("作品投稿時編集キー必須", $c->requirePassword[Configuration::ON_ENTRY]),
				"requirePasswordOnComment" =>
					array("コメント時削除キー必須", $c->requirePassword[Configuration::ON_COMMENT]),
				"requirePostPassword" =>
					array("送信時投稿キー必須", !Util::isEmpty($c->postPassword)),
				"maxTags" =>
					array("最大タグ数", $c->maxTags),
				"foregroundEnabled" =>
					array("文字色使用可否", $c->foregroundEnabled),
				"backgroundEnabled" =>
					array("背景色使用可否", $c->backgroundEnabled),
				"backgroundImageEnabled" =>
					array("背景画像使用可否", $c->backgroundImageEnabled),
				"borderEnabled" =>
					array("枠色使用可否", $c->borderEnabled),
				"subjectSplitting" =>
					array("作品集最大件数", $c->subjectSplitting),
				"rateType" =>
					array("rate 種別", implode("", array_slice(array("((points + 25) / ((evals + 1) * 50)) * 10", "average"), $c->rateType, 1))),
				"updatePeriod" =>
					array("更新印表示日数", $c->updatePeriod),
				"minBodySize" =>
					array("最小本文バイト", $c->minBodySize),
				"maxBodySize" =>
					array("最大本文バイト", $c->maxBodySize),
				"useSummary" =>
					array("概要可否", $c->useSummary),
				"maxSummaryLines" =>
					array("最大概要行数", $c->maxSummaryLines),
				"maxSummarySize" =>
					array("最大概要バイト", $c->maxSummarySize),
				
				"showTitleOnSubject" =>
					array("一覧上作品名表示", $c->showTitle[Configuration::ON_SUBJECT]),
				
				"showNameOnSubject" =>
					array("一覧上名前表示", $c->showName[Configuration::ON_SUBJECT]),
				"showNameOnEntry" =>
					array("作品上名前表示", $c->showName[Configuration::ON_ENTRY]),
				"showNameOnComment" =>
					array("コメント上名前表示", $c->showName[Configuration::ON_COMMENT]),
				
				"showTagsOnSubject" =>
					array("一覧上分類タグ表示", $c->showTags[Configuration::ON_SUBJECT]),
				"showTagsOnEntry" =>
					array("作品上分類タグ表示", $c->showTags[Configuration::ON_ENTRY]),
				
				"showSummaryOnSubject" =>
					array("一覧上概要表示", $c->showSummary[Configuration::ON_SUBJECT]),
				"showSummaryOnEntry" =>
					array("作品上概要表示", $c->showSummary[Configuration::ON_ENTRY]),
					
				"showReadCountOnSubject" =>
					array("一覧上閲覧数表示", $c->showReadCount[Configuration::ON_SUBJECT]),
				"showReadCountOnEntry" =>
					array("作品上閲覧数表示", $c->showReadCount[Configuration::ON_ENTRY]),
				
				"showPointOnSubject" =>
					array("一覧上点数表示", $c->showPoint[Configuration::ON_SUBJECT]),
				"showPointOnEntry" =>
					array("作品上点数表示", $c->showPoint[Configuration::ON_ENTRY]),
				"showPointOnComment" =>
					array("コメント上点数表示", $c->showPoint[Configuration::ON_COMMENT]),
				
				"showRateOnSubject" =>
					array("一覧上 Rate 表示", $c->showRate[Configuration::ON_SUBJECT]),
				"showRateOnEntry" =>
					array("作品上 Rate 表示", $c->showRate[Configuration::ON_ENTRY]),
				
				"showCommentOnSubject" =>
					array("一覧上コメント表示", $c->showComment[Configuration::ON_SUBJECT]),
				"showCommentOnEntry" =>
					array("作品上コメント表示", $c->showComment[Configuration::ON_ENTRY]),
				
				"showSizeOnSubject" =>
					array("一覧上サイズ表示", $c->showSize[Configuration::ON_SUBJECT]),
				"showSizeOnEntry" =>
					array("作品上サイズ表示", $c->showSize[Configuration::ON_ENTRY]),
				
				"showPagesOnSubject" =>
					array("一覧上ページ数表示", $c->showPages[Configuration::ON_SUBJECT]),
				"showPagesOnEntry" =>
					array("作品上ページ数表示", $c->showPages[Configuration::ON_ENTRY]),
			),
		);
		App::closeDB($idb, false, false);
		
		if (App::$handlerType == "json")
		{
			$rt = array();
			
			foreach (Visualizer::$data as $category => $values)
			{
				$list = array();
				
				foreach ($values as $k => $v)
					$list[$k] = is_array($v) ? $v[1] : $v;
				
				$rt[$category] = $list;
			}
			
			return Visualizer::json($rt);
		}
		else
			return Visualizer::visualize();
	}
	
	function fill()
	{
		self::ensureTestMode();
		
		$db = App::openDB();
		$idb = App::openDB(App::INDEX_DATABASE);
		
		$db->beginTransaction();
		
		if ($db !== $idb)
			$idb->beginTransaction();
		
		for ($i = 0; $i < 25; $i++)
		{
			$thread = new Thread($db);
			$thread->entry->id -= rand(100, 10000);
			$thread->entry->title = self::createRandomString(64);
			$thread->entry->name = self::createRandomString(16);
			$thread->entry->mail = self::createRandomString(32);
			$thread->entry->link = self::createRandomString(32);
			
			for ($j = 0; $j < 5; $j++)
				$thread->entry->tags[] = self::createRandomString(16);

			$thread->entry->summary = self::createRandomString(256);
			$thread->body = self::createRandomString(2048 * 2);
			$thread->afterword = self::createRandomString(512);
			
			for ($j = 0; $j < 5; $j++)
				$thread->comment($db, self::createRandomString(32), self::createRandomString(32), self::createRandomString(256), self::createRandomString(32), rand(0, 100), false);
			
			$thread->save($db);
			SearchIndex::register($idb, $thread);
		}
		
		if ($db !== $idb)
			$idb->commit();
		
		$db->commit();
		
		App::closeDB($idb, true);
		App::closeDB($db);
	}
	
	static function createRandomString($length)
	{
		$rt = "";
		
		for ($i = 0; $i < $length; $i++)
			$rt .= rand(0, 9);
		
		return $rt;
	}
	
	/**
	 * @param bool $requireAuth [optional]
	 */
	private static function ensureTestMode($requireAuth = true)
	{
		if (!Configuration::$instance->utilsEnabled)
			throw new ApplicationException("Test utilities are disabled", 403);
		
		Auth::$caption = "管理者ログイン";
		
		if ($requireAuth && !Util::hashEquals(Configuration::$instance->adminHash, Auth::login(true)))
			Auth::loginError("管理者パスワードが一致しません");
	}
}
?>