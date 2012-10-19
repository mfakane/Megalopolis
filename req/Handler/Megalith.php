<?php
class MegalithHandler extends Handler
{
	/**
	 * @var MegalithHandler
	 */
	static $instance;
	
	/**
	 * Megalith compatibility layer (sub/)
	 * @param string $_name Filename
	 */
	function sub($_name = null)
	{
		$name = Util::escapeInput($_name);
		
		if (App::$handlerType == "txt" &&
			preg_match('/^subject(s|[0-9]+|)$/', $name, $matches))
		{
			$db = App::openDB();
			$content = null;
			$latest = Board::getLatestSubject($db);
			
			if ($matches[1] == "s")
			{
				if (Util::isCachedByBrowser(Board::getLastUpdate($db, $latest), $latest))
					return Visualizer::notModified();
				
				$content = implode("\n", array_map(create_function('$_', 'return "subject{$_}.txt";'), array_merge(array(""), $latest > 1 ? range(1, $latest - 1) : array())));
			}
			else
			{
				if (($subject = $matches[1] == "" ? $latest : intval($matches[1])) > $latest)
					throw new ApplicationException("ファイルが見つかりません", 404);
				
				if (($lastUpdate = Board::getLastUpdate($db, $latest)) &&
					Util::isCachedByBrowser($lastUpdate))
					return Visualizer::notModified();
				
				if (Configuration::$instance->showTitle[Configuration::ON_SUBJECT])
				{
					$entries = ThreadEntry::getEntriesBySubject($db, $subject);
					$lastUpdate = max(array_map(create_function('$_', 'return $_->getLatestLastUpdate();'), $entries) + array(0));
				
					if (Util::isCachedByBrowser($lastUpdate))
						return Visualizer::notModified();
					
					$content = implode("\n", array_reverse(array_map(create_function('$_', 'return implode("<>", array_map("htmlspecialchars", array
					(
						"{$_->id}.dat",
						$_->title,
						Configuration::$instance->showName[Configuration::ON_SUBJECT] ? (Util::isEmpty($_->name) ? Configuration::$instance->defaultName : $_->name) : "",
						Configuration::$instance->showName[Configuration::ON_SUBJECT] ? $_->mail : "",
						Configuration::$instance->showName[Configuration::ON_SUBJECT] ? $_->link : "",
						Configuration::$instance->showComment[Configuration::ON_SUBJECT] ? (Configuration::$instance->showPoint[Configuration::ON_SUBJECT] ? "{$_->commentedEvaluationCount}/{$_->responseCount}" : "0/{$_->responseCount}") : "0/0",
						Configuration::$instance->showPoint[Configuration::ON_SUBJECT] ? $_->points : "0",
						Configuration::$instance->showRate[Configuration::ON_SUBJECT] ? $_->rate : "0",
						date("Y/m/d H:i:s", $_->getLatestLastUpdate()),
						"",
						"",
						"",
						"",
						Configuration::$instance->showTags[Configuration::ON_SUBJECT] ? implode(" ", $_->tags) : "",
						Configuration::$instance->showSize[Configuration::ON_SUBJECT] ? $_->size : ""
					)));'), $entries)));
				}
				else
					$content = "";
			}
			
			App::closeDB($db);
			
			return Visualizer::text($content, "Shift_JIS", "Windows-31J");
		}
		else
			throw new ApplicationException("ファイルが見つかりません", 404);
	}
	
	/**
	 * Megalith compatibility layer (dat/)
	 * @param string $_name Filename
	 */
	function dat($_name = null)
	{
		$name = Util::escapeInput($_name);
		
		if (App::$handlerType == "dat")
		{
			$db = App::openDB();
			
			if (!($thread = Thread::load($db, intval($name))))
				throw new ApplicationException("ファイルが見つかりません", 404);
			
			if (Util::isCachedByBrowser($thread->entry->getLatestLastUpdate()))
				return Visualizer::notModified();
			
			if (Cookie::getCookie(Cookie::LAST_ID_KEY) != $thread->entry->id)
			{
				$db->beginTransaction();
				$thread->entry->incrementReadCount($db);
				$db->commit();
				Cookie::setCookie(Cookie::LAST_ID_KEY, $thread->entry->id);
				Cookie::sendCookie();
			}
			
			$c = &Configuration::$instance;
			$_ = &$thread->entry;
			
			if ($c->showTitle[Configuration::ON_SUBJECT])
				$content = array
				(
					implode("<>", array_map("htmlspecialchars", array
					(
						$_->title,
						$c->showName[Configuration::ON_ENTRY] ? (Util::isEmpty($_->name) ? $c->defaultName : $_->name) : "",
						$c->showName[Configuration::ON_ENTRY] ? $_->mail : "",
						$c->showName[Configuration::ON_ENTRY] ? $_->link : "",
						$c->showComment[Configuration::ON_SUBJECT] ? ($c->showPoint[Configuration::ON_SUBJECT] ? "{$_->commentedEvaluationCount}/{$_->responseCount}" : "0/{$_->responseCount}") : "0/0",
						$c->showPoint[Configuration::ON_ENTRY] ? $_->points : "0",
						$c->showRate[Configuration::ON_ENTRY] ? $_->rate : "0",
						date("Y/m/d H:i:s", $_->getLatestLastUpdate()),
						"",
						!Util::isEmpty($thread->backgroundImage) ? $thread->backgroundImage : $thread->background,
						$thread->foreground,
						$thread->convertLineBreak ? "yes" : "no",
						$c->showTags[Configuration::ON_ENTRY] ? implode(" ", $_->tags) : "",
						$c->showSize[Configuration::ON_ENTRY] ? $_->size : ""
					))),
					"",
					str_replace("\r\n", "\n", Visualizer::escapeBody($thread))
				);
			else
				$content = array();
			
			App::closeDB($db);
			
			return Visualizer::text(implode("\n", $content), "Shift_JIS", "Windows-31J");
		}
		else
			throw new ApplicationException("ファイルが見つかりません", 404);
	}
	
	/**
	 * Megalith compatibility layer (com/)
	 * @param string $_name Filename
	 */
	function _com($_name = null)
	{
		$path = explode(".", Util::escapeInput($_name), 2);
		$name = $path[0];
		
		if (App::$handlerType == "dat" && count($path) == 2 && $path[1] == "res")
		{
			$db = App::openDB();
			
			if (!($thread = Thread::load($db, intval($name))))
				throw new ApplicationException("ファイルが見つかりません", 404);
			
			if (Util::isCachedByBrowser($thread->entry->getLatestLastUpdate()))
				return Visualizer::notModified();
			
			if (Configuration::$instance->showComment[Configuration::ON_ENTRY])
				$content = array_merge
				(
					array_map(create_function('$_', 'return implode("<>", array
					(
						"#EMPTY#",
						"",
						"",
						date("Y/m/d H:i:s", $_->dateTime),
						$_->point,
						"",
						"",
						"no"
					));'), $thread->nonCommentEvaluations),
					array_map(create_function('$_', 'return strtr(implode("<>", array_map("htmlspecialchars", array
					(
						$_->body,
						(Util::isEmpty($_->name) ? Configuration::$instance->defaultName : $_->name),
						$_->mail,
						date("Y/m/d H:i:s", $_->dateTime),
						$_->evaluation ? (Configuration::$instance->showPoint[Configuration::ON_COMMENT] ? $_->evaluation->point : 0) : 0,
						"",
						"",
						"no"
					))), array("\r\n" => "<br />", "\r" => "<br />", "\n" => "<br />"));'), $thread->comments)
				);
			else
				$content = array();
			
			App::closeDB($db);
			
			return Visualizer::text(implode("\n", $content), "Shift_JIS", "Windows-31J");
		}
		else
			throw new ApplicationException("ファイルが見つかりません", 404);
	}
	
	/**
	 * Megalith compatibility layer (aft/)
	 * @param string $_name Filename
	 */
	function aft($_name = null)
	{
		$path = explode(".", Util::escapeInput($_name), 2);
		$name = $path[0];
		
		if (App::$handlerType == "dat" && count($path) == 2 && $path[1] == "aft")
		{
			$db = App::openDB();
			
			if (!($thread = Thread::load($db, intval($name))))
				throw new ApplicationException("ファイルが見つかりません", 404);
			
			if (Util::isCachedByBrowser($thread->entry->getLatestLastUpdate()))
				return Visualizer::notModified();
			
			App::closeDB($db);
			
			if (Configuration::$instance->showTitle[Configuration::ON_SUBJECT])
				return Visualizer::text(str_replace("\r\n", "\n", Visualizer::escapeAfterword($thread)), "Shift_JIS", "Windows-31J");
			else
				return Visualizer::text("", "Shift_JIS", "Windows-31J");
		}
		else
			throw new ApplicationException("ファイルが見つかりません", 404);
	}
	
	/**
	 * Megalith compatibility layer (settings)
	 */
	function settings()
	{
		if (App::$handlerType != "ini")
			throw new ApplicationException("ファイルが見つかりません", 404);
		
		if (Util::isCachedByBrowser(filemtime("config.php")))
			return Visualizer::notModified();
		
		return Visualizer::visualize("Index/Settings.Ini", 200, "text/plain", "Shift_JIS", "Windows-31J");
	}
	
	function parseQuery()
	{
		if (isset($_GET["mode"]))
		{
			switch(self::param("mode"))
			{
				case "read":
					return Visualizer::redirect(intval(self::param("log")) . "/" . intval(self::param("key")));
				case "update":
					if (Util::escapeInput(self::param("target", "thread")) == "thread")
						return false;
					else if (Util::escapeInput($_POST["body"]) == "#EMPTY#")
						return App::callHandler("Read", "evaluate", array(intval(self::param("log")), intval(self::param("key"))));
					else
						return App::callHandler("Read", "comment", array(intval(self::param("log")), intval(self::param("key"))));
			}
		}
		else if (isset($_GET["log"]))
			return Visualizer::redirect(intval(self::param("log")));
		else
			return false;
	}
	
	static function param($name, $value = null)
	{
		if (isset($_GET[$name]))
			return Util::escapeInput($_GET[$name]);
		else
			return $value;
	}
}
?>
