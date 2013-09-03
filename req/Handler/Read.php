<?php
class ReadHandler extends Handler
{
	/**
	 * @var ReadHandler
	 */
	static $instance;
	
	public $subject;
	/**
	 * @var ThreadEntry
	 */
	public $entry;
	/**
	 * @var Thread
	 */
	public $thread;
	public $page;
	public $forceTaketori;
	
	function index($_subject = "0", $_id = "0", $_page = "1")
	{
		$subject = intval($_subject);
		$id = intval($_id);
		$page = max(intval($_page), 1);
		$c = &Configuration::$instance;
		
		if (App::$actionName == "index" && Auth::hasSession() && !Auth::hasSession(true))
			Auth::logout();
		else
			Auth::cleanSession(!Auth::hasSession(true));
		
		if (!Auth::hasSession(true) && !$c->showTitle[Configuration::ON_SUBJECT])
			throw new ApplicationException("作品の閲覧は許可されていません", 403);
		
		if (!Auth::hasToken())
			Auth::createToken();
		
		$db = App::openDB("data", false);
		$this->thread = self::loadThread($db, $id);
		$this->subject = $this->thread->subject;
		$this->entry = &$this->thread->entry;
		$this->page = $page;
		
		$history = array_filter(explode(",", Cookie::getCookie(Cookie::VIEW_HISTORY_KEY, "")));
		
		if (!in_array($id, $history))
		{
			$db->beginTransaction();
			$this->entry->incrementReadCount($db);
			$db->commit();
		}
		
		if (($idx = array_search($id, $history)) !== false)
			unset($history[$idx]);
		
		array_unshift($history, $id);
		$history = array_slice($history, 0, Configuration::$instance->maxHistory);
		
		Cookie::setCookie(Cookie::VIEW_HISTORY_KEY, implode(",", $history));
		Cookie::sendCookie();
		
		if (Util::isCachedByBrowser($this->thread->entry->getLatestLastUpdate(), $page . Cookie::getCookie(Cookie::MOBILE_VERTICAL_KEY) . $this->entry->readCount))
			return Visualizer::notModified();
		
		$this->forceTaketori = preg_match('/<\s*font|font:\s*|font-family:\s*/i', $this->thread->body);
		
		if (isset($_POST["admin"]))
		{
			Auth::ensureToken();
			Auth::createToken();
			
			if (!Util::hashEquals(Configuration::$instance->adminHash, Auth::login(true)))
				Auth::loginError("管理者パスワードが一致しません");
			
			$ids = array_map("intval", array_map(array("Util", "escapeInput"), isset($_POST["id"]) ? (is_array($_POST["id"]) ? $_POST["id"] : array($_POST["id"])) : array()));
			$db->beginTransaction();
			
			switch ($mode = Util::escapeInput($_POST["admin"]))
			{
				case "unevaluate":
					foreach ($ids as $i)
						if (isset($this->thread->evaluations[$i]))
							$this->thread->unevaluate($db, $this->thread->evaluations[$i]);
					
					break;
				case "uncomment":
					foreach ($ids as $i)
						if (isset($this->thread->comments[$i]))
							$this->thread->uncomment($db, $this->thread->comments[$i]);
					
					break;
			}
			
			$db->commit();
		}
		
		App::closeDB($db);
		
		if (App::$handlerType == "json")
			return Visualizer::json($this->thread->toArray());
		else
			return Visualizer::visualize("Read/Index");
	}
	
	function _new($_page = null)
	{
		$this->page = !is_null($_page) ? intval($_page) : 1;
		
		$this->thread = new Thread();
		$this->entry = &$this->thread->entry;
		
		if (Configuration::$instance->adminOnly)
		{
			Auth::$caption = "管理者ログイン";
			Auth::$label = "管理者パスワード";
			Auth::$details = '<p class="notify info">管理者のみ新規投稿が可能です。続行するにはパスワードを入力してください</p>';
			
			if (!Util::hashEquals(Configuration::$instance->adminHash, Auth::login(true)))
				Auth::loginError("パスワードが一致しません");
		}
		
		if (!$_POST)
		{
			$this->entry->name = Cookie::getCookie(Cookie::NAME_KEY);
			$this->entry->mail = Cookie::getCookie(Cookie::MAIL_KEY);
			$this->entry->link = Cookie::getCookie(Cookie::LINK_KEY);
		}
		else
		{
			Cookie::setCookie(Cookie::NAME_KEY, self::param("name"));
			Cookie::setCookie(Cookie::MAIL_KEY, self::param("mail"));
			Cookie::setCookie(Cookie::LINK_KEY, self::param("link"));
			Cookie::setCookie(Cookie::PASSWORD_KEY, self::param("editPassword", self::param(Auth::SESSION_PASSWORD)));
			Cookie::sendCookie();
		}
		
		self::setValues($this->entry, $this->thread);
		
		if ($_POST || !is_null($_page))
		{
			Visualizer::$data = self::checkValues($this->entry, $this->thread, false);
			
			if (!is_null($_page) ||
				self::param("preview", null, true) == "true" && !Visualizer::$data)
				return Visualizer::visualize("Read/Index");
		}
		 
		return Visualizer::visualize("Read/Edit");
	}
	
	function edit($_subject = "0", $_id = "0")
	{
		$this->subject = intval($_subject);
		$id = intval($_id);
		$this->page = max(intval($_page = self::param("p", null, true)), 1);
		
		$db = App::openDB();
		$this->thread = self::loadThread($db, $id);
		$this->entry = &$this->thread->entry;
		
		Auth::$caption = "{$this->entry->title} の編集";
		Auth::$label = "編集キー";
		
		if (!Auth::hasSession(true) &&
			!($type = Util::hashEquals(Configuration::$instance->adminHash, $login = Auth::login(false, false))) &&
			!($type = Util::hashEquals($this->thread->hash, $login)))
			Auth::loginError("編集キーが一致しません");
		
		if ($_POST)
		{
			Cookie::setCookie(Cookie::NAME_KEY, self::param("name"));
			Cookie::setCookie(Cookie::MAIL_KEY, self::param("mail"));
			Cookie::setCookie(Cookie::LINK_KEY, self::param("link"));
			Cookie::setCookie(Cookie::PASSWORD_KEY, self::param("editPassword", self::param(Auth::SESSION_PASSWORD)));
			Cookie::sendCookie();
		}
		
		self::setValues($this->entry, $this->thread);
		Visualizer::$data = self::checkValues($this->entry, $this->thread, true);
		
		if (!is_null($_page) ||
			$_POST && self::param("preview", null, true) == "true" && !Visualizer::$data)
		{
			$this->forceTaketori = preg_match('/<\s*font|font:\s*|font-family:\s*/i', $this->thread->body);
			
			return Visualizer::visualize("Read/Index");
		}
		
		if (isset($type) && $type != Util::HASH_TYPE_LATEST)
			Visualizer::$data[] = "サーバに保存されている編集キーの形式が古いため、編集キーを再度入力するか変更することを推奨します。";
		
		Auth::createToken();
		
		return Visualizer::visualize("Read/Edit");
	}
	
	function post($_subject = "0", $_id = "0")
	{
		$subject = intval($_subject);
		$id = intval($_id);
		
		$db = App::openDB();
		$idb = App::openDB(App::INDEX_DATABASE);
		
		$db->beginTransaction();
		
		if ($db !== $idb)
			$idb->beginTransaction();
		
		if ($id == 0)
		{
			$this->thread = new Thread($db);
			
			if (Configuration::$instance->adminOnly)
			{
				Auth::$caption = "管理者ログイン";
				Auth::$label = "管理者パスワード";
				Auth::$details = '<p class="notify info">管理者のみ新規投稿が可能です。続行するにはパスワードを入力してください</p>';
				
				if (!Util::hashEquals(Configuration::$instance->adminHash, Auth::login(true)))
					Auth::loginError("パスワードが一致しません");
			}
		}
		else
		{
			$this->thread = self::loadThread($db, $id);
			
			if (!Auth::hasSession(true) &&
				!($type = Util::hashEquals(Configuration::$instance->adminHash, $login = Auth::login(false, false))) &&
				!($type = Util::hashEquals($this->thread->hash, $login)))
				Auth::loginError("編集キーが一致しません");
		}
		
		$this->entry = &$this->thread->entry;
		self::setValues($this->entry, $this->thread);
		
		if ($id == 0 || !Util::isEmpty(self::param("editPassword")))
			$this->thread->hash = Util::hash(self::param("editPassword"));
		
		$errors = self::checkValues($this->entry, $this->thread, $id != 0);
		
		if ($errors)
			throw new ApplicationException(implode("\r\n", $errors), 400);
		
		$this->thread->save($db);
		
		SearchIndex::register($idb, $this->thread);
		
		if ($db !== $idb)
			$idb->commit();
		
		$db->commit();
		
		App::closeDB($idb);
		App::closeDB($db);
		
		if (!Auth::hasSession(true))
			Auth::logout();
		else
			Auth::cleanSession();
		
		if (Configuration::$instance->showTitle[Configuration::ON_SUBJECT])
			return Visualizer::redirect("{$this->entry->subject}/{$this->entry->id}");
		else
			return Visualizer::visualize("Read/Success");
	}
	
	function unpost($_subject = "0", $_id = "0")
	{
		$this->subject = intval($_subject);
		$id = intval($_id);
		$this->page = 1;
		$isAdmin = Auth::hasSession(true);
		
		$db = App::openDB();
		$idb = App::openDB(App::INDEX_DATABASE);
		
		if (!$_POST)
			if (!$isAdmin)
				Auth::logout();
			else
				Auth::unsetSession();
		
		$this->thread = self::loadThread($db, $id);
		$this->entry = &$this->thread->entry;
		Auth::$caption = "{$this->entry->title} の削除";
		Auth::$label = "編集キー";
		Auth::$details = "<div class='notify warning'>本当に {$this->entry->title} を削除してよろしいですか？続行する場合は編集キーを入力します</div>";
		
		if (!Auth::hasSession(true) &&
			!Util::hashEquals($this->thread->hash, $login = Auth::login(false, false)) &&
			!Util::hashEquals(Configuration::$instance->adminHash, $login))
			Auth::loginError("編集キーが一致しません");
		
		$db->beginTransaction();
		
		if ($db !== $idb)
			$idb->beginTransaction();
		
		$this->thread->delete($db, $idb);
		
		if ($db !== $idb)
			$idb->commit();
		
		$db->commit();
		App::closeDB($idb);
		App::closeDB($db);
		
		if (!$isAdmin)
			Auth::logout();
		else
			Auth::cleanSession();
		
		return Visualizer::redirect("{$this->entry->subject}");
	}
	
	function comment($_subject = "0", $_id = "0")
	{
		$subject = intval($_subject);
		$id = intval($_id);
		$db = App::openDB();
		
		$error = array();
		$name = self::param("name");
		$mail = self::param("mail");
		$body = self::param("body", null, false, false);
		$password = self::param("password", self::param("pass"));
		$postPassword = self::param("postPassword", self::param("compass"));
		$point = intval(self::param("point"));
		
		Cookie::setCookie(Cookie::NAME_KEY, $name);
		Cookie::setCookie(Cookie::MAIL_KEY, $mail);
		Cookie::setCookie(Cookie::PASSWORD_KEY, $password);
		Cookie::sendCookie();
		
		if (Configuration::$instance->requireName[Configuration::ON_COMMENT] && Util::isEmpty($name))
			$error[] = "名前が入力されていません";
			
		if (Util::isEmpty(trim($body)))
			$error[] = "本文が入力されていません";
		
		if ($point != 0 && !in_array($point, Configuration::$instance->commentPointMap))
			$error[] = "評価が不正です";
			
		if (Configuration::$instance->requirePassword[Configuration::ON_COMMENT] && Util::isEmpty($password))
			$error[] = "削除キーが入力されていません";
		
		if (!Util::isEmpty(Configuration::$instance->postPassword))
			if (Util::isEmpty($postPassword))
				$error[] = "投稿キーが入力されていません";
			else if ($postPassword != Configuration::$instance->postPassword)
				$error[] = "投稿キーが一致しません";	
		
		$lock = Util::acquireWriteLock();
		$this->thread = self::loadThread($db, $id);
		$this->entry = &$this->thread->entry;
		
		if ($point && array_filter($this->thread->evaluations, create_function('$_', 'return $_->host == $_SERVER["REMOTE_ADDR"] || $_->host == Util::getRemoteHost();')))
			$error[] = "多重評価はできません";
		
		if (!Auth::hasSession(true) || !Configuration::$instance->ignoreDisallowedWordsWhenAdmin)
		{
			foreach (Configuration::$instance->disallowedWordsForName as $i)
				if (mb_strstr($name, $i))
				{
					if (Configuration::$instance->showDisallowedWords)
						$error[] = "名前に禁止ワードが含まれています: {$i}";
					else
						$error[] = "名前に禁止ワードが含まれています";
						
					break;
				}
			
			foreach (Configuration::$instance->disallowedWordsForComment as $i)
				foreach (array
				(
					"名前" => $name,
					"本文" => $body,
				) as $k => $v)
					if (mb_strstr($v, $i))
					{
						if (Configuration::$instance->showDisallowedWords)
							$error[] = "{$k}に禁止ワードが含まれています: {$i}";
						else
							$error[] = "{$k}に禁止ワードが含まれています";
							
						break;
					}
		}
		
		if ($error)
		{
			App::closeDB($db);
			Util::releaseLock($lock);
			Visualizer::$data = $error;
			header("HTTP/1.1 400 Bad Request");
			
			if (App::$handlerType == "json")
				return Visualizer::json(array
				(
					"error" => $error
				));
			else
				return $this->index($subject, $id);
		}
		else
		{
			$db->beginTransaction();
			$comment = $this->thread->comment($db, $name, $mail, $body, $password, $point);
			$db->commit();
			App::closeDB($db);
			Util::releaseLock($lock);
			
			$history = array_filter(explode(",", Cookie::getCookie(Cookie::EVALUATION_HISTORY_KEY, "")));
			
			if (($idx = array_search($id, $history)) !== false)
				unset($history[$idx]);
			
			array_unshift($history, $id);
			Cookie::setCookie(Cookie::EVALUATION_HISTORY_KEY, implode(",", array_slice($history, 0, Configuration::$instance->maxHistory)));
			Cookie::sendCookie();
			
			
			if (App::$handlerType == "json")
			{
				return Visualizer::json($comment->toArray() + array
				(
					"num" => count($this->thread->comments + $this->thread->nonCommentEvaluations),
					"formattedBody" => Visualizer::escapeSummary($comment->body),
					"deleteAction" => Util::getAbsoluteUrl() . "{$this->entry->subject}/{$this->entry->id}/uncomment?id={$comment->id}"
				));
			}
			else if (Configuration::$instance->showComment[Configuration::ON_ENTRY] && Visualizer::visualizerMode() != "simple")
				return Visualizer::redirect("{$this->entry->subject}/{$this->entry->id}");
			else
				return Visualizer::visualize("Read/Success");
		}
	}
	
	function uncomment($_subject = "0", $_id = "0")
	{
		$subject = intval($_subject);
		$id = intval($_id);
		$commentID = intval(self::param("id", 0, true));
		$isAdmin = Auth::hasSession(true);
		
		if (!$_POST)
			if (!$isAdmin)
				Auth::unsetSession();
			else
				Auth::cleanSession();
		
		$db = App::openDB();
		$this->thread = self::loadThread($db, $id);
		$this->entry = &$this->thread->entry;
		
		if (!($comment = $this->thread->getCommentByID($db, $commentID)))
			throw new ApplicationException("指定された番号 {$commentID} のコメントは {$id} の作品に存在しません", 404);
		
		Auth::$caption = "コメントの削除";
		Auth::$label = "削除キー";
		
		if (!Util::hashEquals(Configuration::$instance->adminHash, $login = Auth::login(false, false)) &&
			!Util::hashEquals($comment->hash, $login))
			Auth::loginError("削除キーが一致しません");
		else if (!$isAdmin)
			Auth::logout();
		else
			Auth::cleanSession();
		
		$db->beginTransaction();
		$this->thread->uncomment($db, $comment);
		$db->commit();
		App::closeDB($db);
		
		if (App::$handlerType == "json")
			return Visualizer::json(null);
		else
			return Visualizer::redirect("{$this->entry->subject}/{$this->entry->id}");
	}
	
	function evaluate($_subject = "0", $_id = "0")
	{
		$subject = intval($_subject);
		$id = intval($_id);
		$db = App::openDB();
		
		$error = array();
		$point = intval(self::param("point"));
	
		if (!in_array($point, Configuration::$instance->pointMap))
			$error[] = "評価が不正です";
		
		if (!Util::isEmpty(Configuration::$instance->postPassword))
			if (Util::isEmpty(self::param("postPassword")))
				$error[] = "投稿キーが入力されていません";
			else if (self::param("postPassword") != Configuration::$instance->postPassword)
				$error[] = "投稿キーが一致しません";	
		
		$lock = Util::acquireWriteLock();
		$this->thread = self::loadThread($db, $id);
		$this->entry = &$this->thread->entry;
			
		if (array_filter($this->thread->evaluations, create_function('$_', 'return $_->host == $_SERVER["REMOTE_ADDR"] || $_->host == Util::getRemoteHost();')))
			$error[] = "多重評価はできません";
		
		if ($error)
		{
			App::closeDB($db);
			Util::releaseLock($lock);
			Visualizer::$data = $error;
			header("HTTP/1.1 400 Bad Request");
			
			if (App::$handlerType == "json")
				return Visualizer::json(array
				(
					"error" => $error
				));
			else
				return $this->index($subject, $id);
		}
		else
		{
			$db->beginTransaction();
			$eval = $this->thread->evaluate($db, $point);
			$db->commit();
			App::closeDB($db);
			Util::releaseLock($lock);
			
			$history = array_filter(explode(",", Cookie::getCookie(Cookie::EVALUATION_HISTORY_KEY, "")));
			
			if (($idx = array_search($id, $history)) !== false)
				unset($history[$idx]);
			
			array_unshift($history, $id);
			Cookie::setCookie(Cookie::EVALUATION_HISTORY_KEY, implode(",", array_slice($history, 0, Configuration::$instance->maxHistory)));
			Cookie::sendCookie();
			
			if (App::$handlerType == "json")
				return Visualizer::json(array
				(
					"id" => intval($eval->id),
					"dateTime" => intval($eval->dateTime),
					"point" => $eval->point
				));
			else if (Configuration::$instance->showPoint[Configuration::ON_ENTRY] && Visualizer::visualizerMode() != "simple")
				return Visualizer::redirect("{$this->entry->subject}/{$this->entry->id}");
			else
				return Visualizer::visualize("Read/Success");
		}
	}

	function unevaluate($_subject = "0", $_id = "0")
	{
		$subject = intval($_subject);
		$id = intval($_id);
		$evaluationID = intval(self::param("id", 0, true));
		
		$db = App::openDB();
		$this->thread = self::loadThread($db, $id);
		$this->entry = &$this->thread->entry;
		
		if (!($eval = $this->thread->getEvaluationByID($db, $evaluationID)))
			throw new ApplicationException("指定された番号 {$evaluationID} の簡易評価は {$id} の作品に存在しません", 404);
		else if ($eval->host != $_SERVER["REMOTE_ADDR"] && $eval->host != Util::getRemoteHost())
			throw new ApplicationException("指定された簡易評価の送信元が現在の送信元と一致しません", 403);
		
		$db->beginTransaction();
		$this->thread->unevaluate($db, $eval);
		$db->commit();
		App::closeDB($db);
		
		if (App::$handlerType == "json")
			return Visualizer::json(null);
		else
			return Visualizer::redirect("{$this->entry->subject}/{$this->entry->id}");
	}
	
	/**
	 * @param int $id
	 * @return Thread
	 */
	private static function loadThread(PDO $db, $id)
	{
		$idb = App::openDB(App::INDEX_DATABASE);
		
		if (!($rt = Thread::loadWithMegalith($db, $idb, $id)))
			throw new ApplicationException("指定された番号 {$id} の作品は存在しません", 404);
		
		App::closeDB($idb);
		
		return $rt;
	}
	
	/**
	 * @param bool $isEdit
	 * @return array
	 */
	private static function checkValues(ThreadEntry $entry, Thread $thread, $isEdit)
	{
		$rt = array();
		
		if (Util::isEmpty($entry->title))
			$rt[] = "作品名が入力されていません";
		
		if (Configuration::$instance->requireName[Configuration::ON_ENTRY] && Util::isEmpty($entry->name))
			$rt[] = "名前が入力されていません";
		
		if (Configuration::$instance->requirePassword[Configuration::ON_ENTRY] && Util::isEmpty(self::param("editPassword")) && !$isEdit)
			$rt[] = "編集キーが入力されていません";
		
		if (count($entry->tags) > Configuration::$instance->maxTags)
			$rt[] = "分類タグは " . Configuration::$instance->maxTags . " 個以内でなければなりません";
		
		if ($m = array_filter($entry->tags, create_function('$_', 'return preg_match("/^([0-9]+|random)$/i", $_);')))
			$rt[] = "次の分類タグは使用できません: " . implode(", ", $m);
		
		if (!Util::isEmpty(Configuration::$instance->postPassword))
			if (Util::isEmpty(self::param("postPassword")))
				$rt[] = "投稿キーが入力されていません";
			else if (self::param("postPassword") != Configuration::$instance->postPassword)
				$rt[] = "投稿キーが一致しません";
		
		if (mb_strstr($entry->link, ":") &&
			!preg_match("/^http:/", trim($entry->link)))
			$rt[] = "リンクに不明なプロトコルが指定されています";
		
		$summaryLines = mb_substr_count(strtr($entry->summary, array("\r\n" => "\n", "\r" => "\n")), "\n") + 1;
		$summaryBytes = strlen(bin2hex(mb_convert_encoding($entry->summary, "Windows-31J", "UTF-8"))) / 2;
		
		if (Configuration::$instance->maxSummaryLines > 0 && $summaryLines > Configuration::$instance->maxSummaryLines)
			$rt[] = "概要が {$summaryLines} 行です。" . Configuration::$instance->maxSummaryLines . " 行以下である必要があります。";
		else if (Configuration::$instance->maxSummarySize > 0 && Configuration::$instance->maxSummarySize < $summaryBytes)
			$rt[] = "概要が {$summaryBytes} バイトです。" . Configuration::$instance->maxSummarySize . " バイト以下である必要があります。";
		
		if (Util::isEmpty(trim($thread->body)))
			$rt[] = "本文が入力されていません";
		else
		{
			$bytes = strlen(bin2hex(mb_convert_encoding($thread->body, "Windows-31J", "UTF-8"))) / 2;
			
			if (Configuration::$instance->minBodySize > 0 && Configuration::$instance->minBodySize > $bytes)
				$rt[] = "本文が {$bytes} バイトです。" . Configuration::$instance->minBodySize . " バイト以上である必要があります。";
			else if (Configuration::$instance->maxBodySize > 0 && Configuration::$instance->maxBodySize < $bytes)
				$rt[] = "本文が {$bytes} バイトです。" . Configuration::$instance->maxBodySize . " バイト以下である必要があります。";
		}
		
		if (!Util::isEmpty($thread->foreground) && !preg_match('/^(#[0-9A-Fa-f]{6}|#[0-9A-Fa-f]{3}|rgba?\s*\(\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*(,\s*[0-9](\.[0-9]+)?\s*)?\))$/', $thread->foreground))
			$rt[] = "文字色の指定が不正です";
		
		if (!Util::isEmpty($thread->background) && !preg_match('/^(#[0-9A-Fa-f]{6}|#[0-9A-Fa-f]{3}|rgba?\s*\(\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*(,\s*[0-9](\.[0-9]+)?\s*)?\))$/', $thread->background))
			$rt[] = "背景色の指定が不正です";
		
		if (!Util::isEmpty($thread->border) && !preg_match('/^(#[0-9A-Fa-f]{6}|#[0-9A-Fa-f]{3}|rgba?\s*\(\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*(,\s*[0-9](\.[0-9]+)?\s*)?\))$/', $thread->border))
			$rt[] = "枠色の指定が不正です";
		
		if (!Auth::hasSession(true) || !Configuration::$instance->ignoreDisallowedWordsWhenAdmin)
		{
			foreach (Configuration::$instance->disallowedWordsForName as $i)
				if (mb_strstr($entry->name, $i))
				{
					if (Configuration::$instance->showDisallowedWords)
						$rt[] = "名前に禁止ワードが含まれています: {$i}";
					else
						$rt[] = "名前に禁止ワードが含まれています";
						
					break;
				}
			
			foreach (Configuration::$instance->disallowedWordsForEntry as $i)
				foreach (array
				(
					"作品名" => $entry->title,
					"分類タグ" => implode(" ", $entry->tags),
					"名前" => $entry->name,
					"概要" => $entry->summary,
					"本文" => $thread->body,
					"あとがき" => $thread->afterword,
				) as $k => $v)
					if (mb_strstr($v, $i))
					{
						if (Configuration::$instance->showDisallowedWords)
							$rt[] = "{$k}に禁止ワードが含まれています: {$i}";
						else
							$rt[] = "{$k}に禁止ワードが含まれています";
							
						break;
					}
		}
		
		return $rt;
	}
	
	private static function setValues(ThreadEntry $entry, Thread $thread)
	{
		if (!is_null(self::param("title")))				$entry->title = self::param("title");
		if (!is_null(self::param("name")))				$entry->name = self::param("name");
		if (!is_null(self::param("mail")))				$entry->mail = self::param("mail");
		if (!is_null(self::param("link")))				$entry->link = self::param("link");
		if (!is_null(self::param("tags")))				$entry->tags = Util::splitTags(self::param("tags"));
		if (!is_null(self::param("summary")))			$entry->summary = self::param("summary", null, false, false);
		if (!is_null(self::param("body")))				$thread->body = self::param("body", null, false, false);
		if (!is_null(self::param("afterword")))			$thread->afterword = self::param("afterword", null, false, false);
		if (!is_null(self::param("foreground")))		$thread->foreground = self::param("foreground") == "#000000" ? null : self::param("foreground");
		if (!is_null(self::param("background")))		$thread->background = self::param("background") == "#000000" ? null : self::param("background");
		if (!is_null(self::param("backgroundImage")))	$thread->backgroundImage = self::param("backgroundImage");
		if (!is_null(self::param("border")))			$thread->border = self::param("border") == "#000000" ? null : self::param("border");
		if (!is_null(self::param("writingMode")))		$thread->writingMode = intval(self::param("writingMode"));
		if (!is_null(self::param("convertLineBreak")))	$thread->convertLineBreak = self::param("convertLineBreak") == "true";
		
		$entry->pageCount = $thread->pageCount();
		$entry->size = round(strlen(bin2hex(mb_convert_encoding($thread->body, "Windows-31J", "UTF-8"))) / 2 / 1024, 2);
		$entry->lastUpdate = time();
		$entry->host = Util::getRemoteHost();
	}
	
	/**
	 * @param string $name
	 * @param string $default
	 * @param bool $tryGet
	 * @return string
	 */
	static function param($name, $default = null, $tryGet = false, $stripLinebreaks = true)
	{
		if (isset($_POST[$name]))
		{
			$rt = Util::escapeInput(is_array($_POST[$name]) ? $_POST[$name][count($_POST[$name]) - 1] : $_POST[$name], $stripLinebreaks);
			
			if ($name != "preview" &&
				$name != "encoded" &&
				$name != "preview" &&
				$name != "p" &&
				strpos($name, "Auth") === false)
				$_SESSION[$name] = $rt;
			
			return $rt;
		}
		else if (isset($_SESSION[$name]))
			return Util::escapeInput($_SESSION[$name], $stripLinebreaks);
		else if ($tryGet && isset($_GET[$name]))
			return Util::escapeInput($_GET[$name], $stripLinebreaks);
		else
			return $default;
	}
}
?>