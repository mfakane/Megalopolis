<?php

namespace Megalopolis;

use \PDO;

class ReadHandler extends Handler
{
	static ReadHandler $instance;

	public ?int $subject = null;
	public ?Thread $thread = null;
	public ?int $page = null;

	/** @psalm-suppress PropertyNotSetInConstructor */
	public ?ThreadEntry $entry {
		/**
		 * @psalm-return ($this->thread is null ? null : mixed)
		 */
		get => $this->thread?->entry;
	}

	function index(string $_subject = "0", string $_id = "0", string $_page = "1"): bool
	{
		$id = intval($_id);
		$page = max(intval($_page), 1);
		$c = Configuration::$instance;

		if (App::$actionName == "index" && Auth::hasSession() && !Auth::hasSession(true))
			Auth::logout();
		else
			Auth::cleanSession(!Auth::hasSession(true));

		if (!Auth::hasSession(true) && !$c->showTitle[Configuration::ON_SUBJECT])
			throw new ApplicationException("作品の閲覧は許可されていません", 403);

		if (!Auth::hasToken())
			Auth::createToken();

		$dh = App::openDB("data");
		$idh = App::openDB(App::INDEX_DATABASE);

		$thread = $dh->withTransactionCombo($idh, fn($db, $idb) => self::loadThread($db, $idb, $id));
		$this->thread = &$thread;
		$this->subject = $thread->subject;
		$this->page = $page;

		$history = array_filter(explode(",", Cookie::getCookie(Cookie::VIEW_HISTORY_KEY, "")));

		if (!in_array($id, $history)) {
			$dh->withTransaction(fn($db) => $thread->entry->incrementReadCount($db));
		}

		if (($idx = array_search($id, $history)) !== false)
			unset($history[$idx]);

		array_unshift($history, $id);
		$history = array_slice($history, 0, Configuration::$instance->maxHistory);

		Cookie::setCookie(Cookie::VIEW_HISTORY_KEY, implode(",", $history));
		Cookie::sendCookie();

		if (Util::isCachedByBrowser($thread->entry->getLatestLastUpdate(), $page . Cookie::getCookie(Cookie::MOBILE_VERTICAL_KEY, "") . $thread->entry->readCount))
			Visualizer::notModified();

		if (isset($_POST["admin"]) && is_string($_POST["admin"])) {
			Auth::ensureToken();
			Auth::createToken();

			if (Util::hashEquals(Configuration::$instance->adminHash ?? "", Auth::login(true)) === false)
				Auth::loginError("管理者パスワードが一致しません");

			$ids = array_map(fn(string $x) => intval($x), IndexHandler::postParamAsArray("id", []));

			$_post_admin = Util::escapeInput($_POST["admin"]);
			$dh->withTransaction(function ($db) use ($ids, $_post_admin, $thread) {
				switch ($_post_admin) {
					case "unevaluate":
						foreach ($ids as $i)
							if (isset($thread->evaluations[$i]))
								$thread->unevaluate($db, $thread->evaluations[$i]);

						break;
					case "uncomment":
						foreach ($ids as $i)
							if (isset($thread->comments[$i]))
								$thread->uncomment($db, $thread->comments[$i]);

						break;
				}
			});
		}

		$idh->close();
		$dh->close();

		if (App::$handlerType == "json")
			return Visualizer::json($thread->toArray());
		else
			return Visualizer::visualize("Read/Index");
	}

	function _new(?string $_page = null): bool
	{
		$this->page = !is_null($_page) ? intval($_page) : max(intval($_page = self::param("p", tryGet: true)), 1);

		$entry = new ThreadEntry(0);
		$thread = new Thread($entry);
		$this->thread = &$thread;

		if (Configuration::$instance->adminOnly) {
			Auth::$caption = "管理者ログイン";
			Auth::$label = "管理者パスワード";
			Auth::$details = '<p class="notify info">管理者のみ新規投稿が可能です。続行するにはパスワードを入力してください</p>';

			if (Util::hashEquals(Configuration::$instance->adminHash ?? "", Auth::login(true)) === false)
				Auth::loginError("パスワードが一致しません");
		}

		if (!$_POST) {
			$entry->name = Cookie::getCookie(Cookie::NAME_KEY);
			$entry->mail = Cookie::getCookie(Cookie::MAIL_KEY);
			$entry->link = Cookie::getCookie(Cookie::LINK_KEY);
		} else {
			Cookie::setCookie(Cookie::NAME_KEY, self::param("name", ""));
			Cookie::setCookie(Cookie::MAIL_KEY, self::param("mail", ""));
			Cookie::setCookie(Cookie::LINK_KEY, self::param("link", ""));
			Cookie::setCookie(Cookie::PASSWORD_KEY, self::param("editPassword", self::param(Auth::SESSION_PASSWORD, "")));
			Cookie::sendCookie();
		}

		self::setValues($entry, $thread);

		if ($_POST || $_page !== null) {
			Visualizer::$data = self::checkValues($entry, $thread, false);

			if (
				$_page !== null ||
				self::param("preview", null, true) == "true" && !Visualizer::$data
			)
				return Visualizer::visualize("Read/Index");
		}

		return Visualizer::visualize("Read/Edit");
	}

	function edit(string $_subject = "0", string $_id = "0"): bool
	{
		$this->subject = intval($_subject);
		$id = intval($_id);
		$this->page = max(intval($_page = self::param("p", tryGet: true)), 1);

		$dh = App::openDB();
		$idh = App::openDB(App::INDEX_DATABASE);

		$thread = $dh->withTransactionCombo($idh, fn($db, $idb) => self::loadThread($db, $idb, $id));
		$this->thread = &$thread;

		$idh->close();
		$dh->close();

		Auth::$caption = "{$thread->entry->title} の編集";
		Auth::$label = "編集キー";

		if (
			!Auth::hasSession(true) &&
			($type = Util::hashEquals(Configuration::$instance->adminHash ?? "", $login = Auth::login(false, false))) === false &&
			($type = Util::hashEquals($thread->hash ?? "", $login)) === false
		)
			Auth::loginError("編集キーが一致しません");

		if ($_POST) {
			Cookie::setCookie(Cookie::NAME_KEY, self::param("name", ""));
			Cookie::setCookie(Cookie::MAIL_KEY, self::param("mail", ""));
			Cookie::setCookie(Cookie::LINK_KEY, self::param("link", ""));
			Cookie::setCookie(Cookie::PASSWORD_KEY, self::param("editPassword", self::param(Auth::SESSION_PASSWORD, "")));
			Cookie::sendCookie();
		}

		self::setValues($thread->entry, $thread);
		Visualizer::$data = self::checkValues($thread->entry, $thread, true);

		if (
			$_page !== null ||
			$_POST && self::param("preview", null, true) == "true" && !Visualizer::$data
		) {
			return Visualizer::visualize("Read/Index");
		}

		if (isset($type) && $type != Util::HASH_TYPE_LATEST)
			Visualizer::$data[] = "サーバに保存されている編集キーの形式が古いため、編集キーを再度入力するか変更することを推奨します。";

		Auth::createToken();

		return Visualizer::visualize("Read/Edit");
	}

	function post(string $_subject = "0", string $_id = "0"): bool
	{
		$id = intval($_id);

		$dh = App::openDB();
		$idh = App::openDB(App::INDEX_DATABASE);

		$thread = $dh->withTransactionCombo(
			$idh,
			function ($db, $idb) use ($id) {
				if ($id == 0) {
					$entry = ThreadEntry::create($db);
					$this->thread = new Thread($entry);
					assert($this->entry instanceof ThreadEntry);

					if (Configuration::$instance->adminOnly) {
						Auth::$caption = "管理者ログイン";
						Auth::$label = "管理者パスワード";
						Auth::$details = '<p class="notify info">管理者のみ新規投稿が可能です。続行するにはパスワードを入力してください</p>';

						if (Util::hashEquals(Configuration::$instance->adminHash ?? "", Auth::login(true)) === false)
							Auth::loginError("パスワードが一致しません");
					}
				} else {
					$this->thread = self::loadThread($db, $idb, $id);
					assert($this->entry instanceof ThreadEntry);

					if (
						!Auth::hasSession(true) &&
						($type = Util::hashEquals(Configuration::$instance->adminHash ?? "", $login = Auth::login(false, false))) === false &&
						($type = Util::hashEquals($this->thread->hash ?? "", $login)) === false
					)
						Auth::loginError("編集キーが一致しません");
				}

				self::setValues($this->entry, $this->thread);

				$editPassword = self::param("editPassword", "");
				if ($id == 0 || $editPassword != "")
					$this->thread->hash = Util::hash($editPassword);

				$errors = self::checkValues($this->entry, $this->thread, $id != 0);

				if ($errors)
					throw new ApplicationException(implode("\r\n", $errors), 400);

				$this->thread->save($db);

				SearchIndex::register($idb, $this->thread);

				return $this->thread;
			}
		);

		$idh->close();
		$dh->close();

		if (!Auth::hasSession(true))
			Auth::logout();
		else
			Auth::cleanSession();

		if (Configuration::$instance->showTitle[Configuration::ON_SUBJECT])
			return Visualizer::redirect("{$thread->subject}/{$thread->id}");
		else
			return Visualizer::visualize("Read/Success");
	}

	function unpost(string $_subject = "0", string $_id = "0"): bool
	{
		$this->subject = intval($_subject);
		$id = intval($_id);
		$this->page = 1;
		$isAdmin = Auth::hasSession(true);

		$dh = App::openDB();
		$idh = App::openDB(App::INDEX_DATABASE);

		if (!$_POST)
			if (!$isAdmin)
				Auth::logout();
			else
				Auth::unsetSession();

		$thread = $dh->withTransactionCombo($idh, fn($db, $idb) => self::loadThread($db, $idb, $id));
		$this->thread = &$thread;

		Auth::$caption = "{$thread->entry->title} の削除";
		Auth::$label = "編集キー";
		Auth::$details = "<div class='notify warning'>本当に {$thread->entry->title} を削除してよろしいですか？続行する場合は編集キーを入力します</div>";

		if (
			!Auth::hasSession(true) &&
			Util::hashEquals($this->thread->hash ?? "", $login = Auth::login(false, false)) === false &&
			Util::hashEquals(Configuration::$instance->adminHash ?? "", $login) === false
		)
			Auth::loginError("編集キーが一致しません");

		$dh->withTransactionCombo(
			$idh,
			fn($db, $idb) => $thread->delete($db, $idb)
		);

		$idh->close();
		$dh->close();

		if (!$isAdmin)
			Auth::logout();
		else
			Auth::cleanSession();

		return Visualizer::redirect("{$thread->subject}");
	}

	function comment(string $_subject = "0", string $_id = "0"): bool
	{
		$subject = intval($_subject);
		$id = intval($_id);
		$dh = App::openDB();
		$idh = App::openDB(App::INDEX_DATABASE);

		$error = array();
		$name = self::param("name", "");
		$mail = self::param("mail", "");
		$body = self::param("body", "", false, false);
		$password = self::param("password", self::param("pass", ""));
		$postPassword = self::param("postPassword", self::param("compass", ""));
		$point = intval(self::param("point", "0"));

		Cookie::setCookie(Cookie::NAME_KEY, $name);
		Cookie::setCookie(Cookie::MAIL_KEY, $mail);
		Cookie::setCookie(Cookie::PASSWORD_KEY, $password);
		Cookie::sendCookie();

		if (Configuration::$instance->requireName[Configuration::ON_COMMENT] && $name == "")
			$error[] = "名前が入力されていません";

		if (trim($body) == "")
			$error[] = "本文が入力されていません";

		if ($point != 0 && !in_array($point, Configuration::$instance->commentPointMap))
			$error[] = "評価が不正です";

		if (Configuration::$instance->requirePassword[Configuration::ON_COMMENT] && $password == "")
			$error[] = "削除キーが入力されていません";

		if (isset(Configuration::$instance->postPassword) && Configuration::$instance->postPassword != "")
			if ($postPassword == "")
				$error[] = "投稿キーが入力されていません";
			else if ($postPassword != Configuration::$instance->postPassword)
				$error[] = "投稿キーが一致しません";

		$lock = Util::acquireWriteLock();
		$thread = $dh->withTransactionCombo($idh, fn($db, $idb) => self::loadThread($db, $idb, $id));
		$this->thread = &$thread;

		if ($point && array_filter($thread->evaluations, fn($x) => Util::remoteHostMatches($x->host)))
			$error[] = "多重評価はできません";

		if (!Auth::hasSession(true) || !Configuration::$instance->ignoreDisallowedWordsWhenAdmin) {
			foreach (Configuration::$instance->disallowedWordsForName as $i)
				if (mb_strstr($name, $i) !== false) {
					if (Configuration::$instance->showDisallowedWords)
						$error[] = "名前に禁止ワードが含まれています: {$i}";
					else
						$error[] = "名前に禁止ワードが含まれています";

					break;
				}

			foreach (Configuration::$instance->disallowedWordsForComment as $i)
				foreach (
					array(
						"名前" => $name,
						"本文" => $body,
					) as $k => $v
				)
					if (mb_strstr($v, $i) !== false) {
						if (Configuration::$instance->showDisallowedWords)
							$error[] = "{$k}に禁止ワードが含まれています: {$i}";
						else
							$error[] = "{$k}に禁止ワードが含まれています";

						break;
					}
		}

		if ($error) {
			$idh->close();
			$dh->close();
			Util::releaseLock($lock);
			Visualizer::$data = $error;
			header("HTTP/1.1 400 Bad Request");

			if (App::$handlerType == "json")
				return Visualizer::json(array(
					"error" => $error
				));
			else
				return $this->index(strval($subject), strval($id));
		} else {
			$comment = $dh->withTransaction(fn($db) => $thread->comment($db, $name, $mail, $body, $password, $point));
			$idh->close();
			$dh->close();
			Util::releaseLock($lock);

			$history = array_filter(explode(",", Cookie::getCookie(Cookie::EVALUATION_HISTORY_KEY, "")));

			if (($idx = array_search($id, $history)) !== false)
				unset($history[$idx]);

			array_unshift($history, $id);
			Cookie::setCookie(Cookie::EVALUATION_HISTORY_KEY, implode(",", array_slice($history, 0, Configuration::$instance->maxHistory)));
			Cookie::sendCookie();


			if (App::$handlerType == "json") {
				return Visualizer::json($comment->toArray() + array(
					"num" => count($thread->comments + $thread->nonCommentEvaluations),
					"formattedBody" => Visualizer::escapeSummary($comment->body ?? ""),
					"deleteAction" => Util::getAbsoluteUrl() . "{$thread->subject}/{$thread->id}/uncomment?id={$comment->id}"
				));
			} else if (Configuration::$instance->showComment[Configuration::ON_ENTRY] && Visualizer::visualizerMode() != "simple")
				return Visualizer::redirect("{$thread->subject}/{$thread->id}");
			else
				return Visualizer::visualize("Read/Success");
		}
	}

	function uncomment(string $_subject = "0", string $_id = "0"): bool
	{
		$id = intval($_id);
		$commentID = intval(self::param("id", "0", true));
		$isAdmin = Auth::hasSession(true);

		if (!$_POST)
			if (!$isAdmin)
				Auth::unsetSession();
			else
				Auth::cleanSession();

		$dh = App::openDB();
		$idh = App::openDB(App::INDEX_DATABASE);
		$thread = $dh->withTransactionCombo($idh, fn($db, $idb) => self::loadThread($db, $idb, $id));
		$this->thread = &$thread;

		$comment = $dh->execute(fn($db) => $thread->getCommentByID($db, $commentID));
		if (!$comment)
			throw new ApplicationException("指定された番号 {$commentID} のコメントは {$id} の作品に存在しません", 404);

		Auth::$caption = "コメントの削除";
		Auth::$label = "削除キー";

		if (
			Util::hashEquals(Configuration::$instance->adminHash ?? "", $login = Auth::login(false, false)) === false &&
			Util::hashEquals($comment->hash ?? "", $login) === false
		)
			Auth::loginError("削除キーが一致しません");
		else if (!$isAdmin)
			Auth::logout();
		else
			Auth::cleanSession();

		$dh->withTransaction(fn($db) => $thread->uncomment($db, $comment));
		$idh->close();
		$dh->close();

		if (App::$handlerType == "json")
			return Visualizer::json(null);
		else
			return Visualizer::redirect("{$thread->subject}/{$thread->id}");
	}

	function evaluate(string $_subject = "0", string $_id = "0"): bool
	{
		$subject = intval($_subject);
		$id = intval($_id);
		$dh = App::openDB();
		$idh = App::openDB(App::INDEX_DATABASE);

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
		$thread = $dh->withTransactionCombo($idh, fn($db, $idb) => self::loadThread($db, $idb, $id));
		$this->thread = &$thread;

		if (array_filter($thread->evaluations, fn($x) => Util::remoteHostMatches($x->host)))
			$error[] = "多重評価はできません";

		if ($error) {
			$idh->close();
			$dh->close();
			Util::releaseLock($lock);
			Visualizer::$data = $error;
			header("HTTP/1.1 400 Bad Request");

			if (App::$handlerType == "json")
				return Visualizer::json(array(
					"error" => $error
				));
			else
				return $this->index(strval($subject), strval($id));
		} else {
			$eval = $dh->withTransaction(fn($db) => $thread->evaluate($db, $point));
			$idh->close();
			$dh->close();
			Util::releaseLock($lock);

			$history = array_filter(explode(",", Cookie::getCookie(Cookie::EVALUATION_HISTORY_KEY, "")));

			if (($idx = array_search($id, $history)) !== false)
				unset($history[$idx]);

			array_unshift($history, $id);
			Cookie::setCookie(Cookie::EVALUATION_HISTORY_KEY, implode(",", array_slice($history, 0, Configuration::$instance->maxHistory)));
			Cookie::sendCookie();

			if (App::$handlerType == "json")
				return Visualizer::json(array(
					"id" => intval($eval->id),
					"dateTime" => intval($eval->dateTime),
					"point" => $eval->point
				));
			else if (Configuration::$instance->showPoint[Configuration::ON_ENTRY] && Visualizer::visualizerMode() != "simple")
				return Visualizer::redirect("{$thread->subject}/{$thread->id}");
			else
				return Visualizer::visualize("Read/Success");
		}
	}

	function unevaluate(string $_subject = "0", string $_id = "0"): bool
	{
		$id = intval($_id);
		$evaluationID = intval(self::param("id", "0", true));

		$dh = App::openDB();
		$idh = App::openDB(App::INDEX_DATABASE);

		$thread = $dh->withTransactionCombo($idh, fn($db, $idb) => self::loadThread($db, $idb, $id));
		$this->thread = &$thread;

		$eval = $dh->execute(fn($db) => $thread->getEvaluationByID($db, $evaluationID));

		if (!$eval)
			throw new ApplicationException("指定された番号 {$evaluationID} の簡易評価は {$id} の作品に存在しません", 404);
		else if (!Util::remoteHostMatches($eval->host))
			throw new ApplicationException("指定された簡易評価の送信元が現在の送信元と一致しません", 403);

		$dh->withTransaction(fn($db) => $thread->unevaluate($db, $eval));
		$idh->close();
		$dh->close();

		if (App::$handlerType == "json")
			return Visualizer::json(null);
		else
			return Visualizer::redirect("{$thread->subject}/{$thread->id}");
	}

	private static function loadThread(PDO $db, PDO $idb, int $id): Thread
	{
		$rt = Thread::loadWithMegalith($db, $idb, $id);

		if (!$rt)
			throw new ApplicationException("指定された番号 {$id} の作品は存在しません", 404);

		return $rt;
	}

	/**
	 * @return string[]
	 */
	private static function checkValues(ThreadEntry $entry, Thread $thread, bool $isEdit): array
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

		if ($m = array_filter($entry->tags, function ($_) {
			return preg_match("/^([0-9]+|random)$/i", $_);
		}))
			$rt[] = "次の分類タグは使用できません: " . implode(", ", $m);

		if (!Util::isEmpty(Configuration::$instance->postPassword))
			if (Util::isEmpty(self::param("postPassword")))
				$rt[] = "投稿キーが入力されていません";
			else if (self::param("postPassword") != Configuration::$instance->postPassword)
				$rt[] = "投稿キーが一致しません";

		if (
			isset($entry->link) &&
			mb_strstr($entry->link, ":") !== false &&
			!preg_match("/^http:/", trim($entry->link))
		)
			$rt[] = "リンクに不明なプロトコルが指定されています";

		$summaryLines = mb_substr_count(strtr($entry->summary ?? "", array("\r\n" => "\n", "\r" => "\n")), "\n") + 1;
		$summaryBytes = strlen(bin2hex((string)mb_convert_encoding($entry->summary ?? "", "Windows-31J", "UTF-8"))) / 2;

		if (Configuration::$instance->maxSummaryLines > 0 && $summaryLines > Configuration::$instance->maxSummaryLines)
			$rt[] = "概要が {$summaryLines} 行です。" . Configuration::$instance->maxSummaryLines . " 行以下である必要があります。";
		else if (Configuration::$instance->maxSummarySize > 0 && Configuration::$instance->maxSummarySize < $summaryBytes)
			$rt[] = "概要が {$summaryBytes} バイトです。" . Configuration::$instance->maxSummarySize . " バイト以下である必要があります。";

		if (Util::isEmpty(trim($thread->body ?? "")))
			$rt[] = "本文が入力されていません";
		else {
			$bytes = strlen(bin2hex((string)mb_convert_encoding($thread->body ?? "", "Windows-31J", "UTF-8"))) / 2;

			if (Configuration::$instance->minBodySize > 0 && Configuration::$instance->minBodySize > $bytes)
				$rt[] = "本文が {$bytes} バイトです。" . Configuration::$instance->minBodySize . " バイト以上である必要があります。";
			else if (Configuration::$instance->maxBodySize > 0 && Configuration::$instance->maxBodySize < $bytes)
				$rt[] = "本文が {$bytes} バイトです。" . Configuration::$instance->maxBodySize . " バイト以下である必要があります。";
		}

		if (!Util::isEmpty($thread->foreground) && !preg_match('/^(#[0-9A-Fa-f]{6}|#[0-9A-Fa-f]{3}|rgba?\s*\(\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*(,\s*[0-9](\.[0-9]+)?\s*)?\))$/', $thread->foreground ?? ""))
			$rt[] = "文字色の指定が不正です";

		if (!Util::isEmpty($thread->background) && !preg_match('/^(#[0-9A-Fa-f]{6}|#[0-9A-Fa-f]{3}|rgba?\s*\(\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*(,\s*[0-9](\.[0-9]+)?\s*)?\))$/', $thread->background ?? ""))
			$rt[] = "背景色の指定が不正です";

		if (!Util::isEmpty($thread->border) && !preg_match('/^(#[0-9A-Fa-f]{6}|#[0-9A-Fa-f]{3}|rgba?\s*\(\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*(,\s*[0-9](\.[0-9]+)?\s*)?\))$/', $thread->border ?? ""))
			$rt[] = "枠色の指定が不正です";

		if (!Auth::hasSession(true) || !Configuration::$instance->ignoreDisallowedWordsWhenAdmin) {
			foreach (Configuration::$instance->disallowedWordsForName as $i)
				if (mb_strstr($entry->name ?? "", $i) !== false) {
					if (Configuration::$instance->showDisallowedWords)
						$rt[] = "名前に禁止ワードが含まれています: {$i}";
					else
						$rt[] = "名前に禁止ワードが含まれています";

					break;
				}

			foreach (Configuration::$instance->disallowedWordsForEntry as $i)
				foreach (
					array(
						"作品名" => $entry->title,
						"分類タグ" => implode(" ", $entry->tags),
						"名前" => $entry->name,
						"概要" => $entry->summary,
						"本文" => $thread->body,
						"あとがき" => $thread->afterword,
					) as $k => $v
				)
					if (mb_strstr($v ?? "", $i) !== false) {
						if (Configuration::$instance->showDisallowedWords)
							$rt[] = "{$k}に禁止ワードが含まれています: {$i}";
						else
							$rt[] = "{$k}に禁止ワードが含まれています";

						break;
					}
		}

		return $rt;
	}

	private static function setValues(ThreadEntry $entry, Thread $thread): void
	{
		if (!is_null(self::param("title")))				$entry->title = self::param("title");
		if (!is_null(self::param("name")))				$entry->name = self::param("name");
		if (!is_null(self::param("mail")))				$entry->mail = self::param("mail");
		if (!is_null(self::param("link")))				$entry->link = self::param("link");
		if (!is_null(self::param("tags")))				$entry->tags = Util::splitTags(self::param("tags", ""));
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
		$entry->size = round((float)strlen(bin2hex((string)mb_convert_encoding($thread->body ?? "", "Windows-31J", "UTF-8"))) / 2.0 / 1024.0, 2);
		$entry->lastUpdate = time();
		$entry->host = Util::getRemoteHost() ?? null;
	}

	/**
	 * @template T of string|?string
	 * @param T $default
	 * @return (T is string ? string : ?string)
	 */
	static function param(string $name, ?string $default = null, bool $tryGet = false, bool $stripLinebreaks = true): ?string
	{
		if (isset($_POST[$name])) {
			$input_array = !is_array($_POST[$name]) ? [$_POST[$name]] : $_POST[$name];
			$input = null;
			array_walk_recursive($input_array, function (mixed &$x) use (&$input) {
				$input ??= strval($x);
			});
			$input ??= $default;
			$rt = Util::escapeInput($input ?? "", $stripLinebreaks);

			if (
				$name != "preview" &&
				$name != "encoded" &&
				$name != "preview" &&
				$name != "p" &&
				strpos($name, "Auth") === false
			)
				$_SESSION[$name] = $rt;

			return $rt;
		} else if (isset($_SESSION[$name]))
			return Util::escapeInput($_SESSION[$name], $stripLinebreaks);
		else if ($tryGet && isset($_GET[$name])) {
			$input_array = !is_array($_GET[$name]) ? [$_GET[$name]] : $_GET[$name];
			$input = null;
			array_walk_recursive($input_array, function (mixed &$x) use (&$input) {
				$input ??= strval($x);
			});
			$input ??= $default;
			$rt = Util::escapeInput($input ?? "", $stripLinebreaks);

			return $rt;
		} else
			return $default;
	}
}
