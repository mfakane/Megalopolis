<?php
class UtilHandler extends Handler
{
	/**
	 * @var UtilHandler
	 */
	static $instance;
	
	function index()
	{
		self::ensureTestMode(false);
		
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
	
	function convert()
	{
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
		
			if ($params[0] == "list")
			{
				$subjects = array_merge(array_slice(file("{$dir}sub/subjects.txt"), 1), array("subject.txt"));
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
					$stats = explode("\n", trim(file_get_contents($subjectFile)));
					$count = count($stats);
					
					$set = array
					(
						"start" => $subjectNum > 0
							? (is_file($previousSubjectFile) ? self::getDataLineID(array_pop(explode("\n", trim(file_get_contents($previousSubjectFile))))) + 1 : self::getDataLineID($stats[0]))
							: 0,
						"end" => $subjectNum < count($subjects)
							? (is_file($nextSubjectFile) ? self::getDataLineID(array_shift(file($nextSubjectFile))) : self::getDataLineID($stats[$count - 1]) + 1)
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
						"count" => 0
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
				$currentCount = 0;
				$firstID = 0;
				
				foreach (new DirectoryIterator("{$dir}dat") as $i)
					if ($i->isFile() &&
						mb_strstr($i->getFilename(), ".") == ".dat" &&
						($id = intval(mb_substr($i->getFilename(), 0, -4))) >= $start &&
						($end == 0 || $id < $end))
					{
						$thread = Util::convertAndSaveToThread($db, $idb, $subject, "{$dir}dat/{$id}.dat", "{$dir}com/{$id}.res.dat", "{$dir}aft/{$id}.aft.dat");
						
						if (!$thread)
							continue;
						
						if ($firstID == 0)
							$firstID = $thread->id;
						
						$count++;
						
						if (++$currentCount == max(Configuration::$instance->convertDivision, 1))
						{
							$lastID = $thread->id + 1;
							array_unshift($params, "{$subject}-{$lastID}-{$end}");
							
							break;
						}
					}
				
				App::closeDB($idb);
				App::closeDB($db);
				
				if (App::$handlerType == "json")
					return Visualizer::json(array
					(
						"first" => $firstID,
						"remaining" => $params,
						"count" => $count
					));
				else
					return Visualizer::redirect("util/convert?p=" . urlencode(implode(",", $params)) . "&c={$count}");
			}
		}
		else
			return Visualizer::visualize();
	}
	
	private static function getDataLineID($s)
	{
		return intval(mb_substr($s, 0, mb_strpos($s, ".")));
	}
	
	function config()
	{
		$c = Configuration::$instance;
		Visualizer::$data = array
		(
			"system" => array
			(
				lcfirst(App::NAME) => App::VERSION,
				"megalith" => App::MEGALITH_VERSION,
				"php" => phpversion(),
			),
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
					array("一覧上分類タグ表示", $c->showTags[Configuration::ON_SUBJECT]),
				"showSummaryOnEntry" =>
					array("作品上分類タグ表示", $c->showTags[Configuration::ON_ENTRY]),
				
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
		$db = App::openDB();
		
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
		}
		
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