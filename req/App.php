<?php
class App
{
	const NAME = "Megalopolis";
	const VERSION = 46;
	const MEGALITH_VERSION = 50;

	const META_TABLE = "meta";
	const SUBJECT_TABLE = "subject";
	const THREAD_ENTRY_TABLE = "threadEntry";
	const THREAD_EVALUATION_TABLE = "threadEvaluation";
	const THREAD_TAG_TABLE = "threadTag";
	const THREAD_TABLE = "thread";
	const THREAD_STYLE_TABLE = "threadStyle";
	const THREAD_PASSWORD_TABLE = "threadPassword";
	const COMMENT_TABLE = "comment";
	const EVALUATION_TABLE = "evaluation";
	const AUTHOR_TABLE = "author";
	const TAG_TABLE = "tags";
	const SESSION_STORE_TABLE = "sessionStore";
	const INDEX_DATABASE = "search";

	static $handler;
	static $handlerName;
	static $actionName;
	static $handlerType = "html";
	static $pathInfo = array();
	static $startTime;

	/**
	 * @param bool $cond [optional]
	 * @param string $desc [optional]
	 */
	static function precondition($cond = true, $desc = null)
	{
		if ($desc == null)
		{
			self::precondition(version_compare(PHP_VERSION, "5.2.5", ">="), "PHP 5.2.5");
			self::precondition(extension_loaded("mbstring"), "mbstring");
			self::precondition(extension_loaded("pdo"), "PDO");
			self::precondition(in_array(Util::HASH_ALGORITHM, hash_algos()), "hash_algos() " . Util::HASH_ALGORITHM);

			mb_language("Japanese");
			mb_internal_encoding("UTF-8");
			mb_http_output("UTF-8");
			mb_regex_encoding("UTF-8");
			ignore_user_abort(true);

			if (!function_exists("lcfirst"))
			{
				function lcfirst($str)
				{
					$str[0] = strtolower($str[0]);

					return $str;
				}

			}

			if (!function_exists("ctype_digit"))
			{
				function ctype_digit($str)
				{
					return preg_match('/^[0-9]+$/', $str);
				}

			}
		}
		else if (!$cond)
			throw new ApplicationException("Precondition {$desc} failed.");
	}

	static function stripMagicQuotesSlashes()
	{
		if (get_magic_quotes_gpc())
		{
			$_GET = self::stripSlashesRecursive($_GET);
			$_POST = self::stripSlashesRecursive($_POST);
			$_REQUEST = self::stripSlashesRecursive($_REQUEST);
			$_COOKIE = self::stripSlashesRecursive($_COOKIE);
		}
	}

	private static function stripSlashesRecursive($arg)
	{
		if (is_array($arg))
			return array_map(array("self", "stripSlashesRecursive"), $arg);
		else
			return stripslashes($arg);
	}

	private static function isBBQed()
	{
		return substr_count($_SERVER["REMOTE_ADDR"], ".") == 3 && gethostbyname(implode(".", array_reverse(explode(".", $_SERVER["REMOTE_ADDR"]))) . ".niku.2ch.net") == "127.0.0.2";
	}

	private static function matchesAddress($arr)
	{
		$addr = $_SERVER["REMOTE_ADDR"];
		$host = Util::getRemoteHost();

		foreach ($arr as $i)
			if (Util::wildcard($i, $addr) || Util::wildcard($i, $host))
				return true;

		return false;
	}

	private static function sCRYed()
	{
		// ススススクライド
	}

	static function main()
	{
		try
		{
			if (!is_writable(DATA_DIR))
				throw new ApplicationException(DATA_DIR . " が書き込み可能ではありません");
			
			if ($_POST)
			{
				if (!Configuration::$instance->allowWrite || !self::matchesAddress(Configuration::$instance->allowWrite))
				{
					if (Util::getBrowserType() != Util::BROWSER_TYPE_MOBILE && (!isset($_SERVER["HTTP_REFERER"]) || mb_strpos($_SERVER["HTTP_REFERER"], Util::getAbsoluteUrl()) != 0))
						throw new ApplicationException("不正な送信元です", 403);

					if ((Configuration::$instance->useBBQ & Configuration::BBQ_WRITE) && self::isBBQed())
						throw new ApplicationException("公開プロキシを使用した送信は規制されています", 403);

					if (Configuration::$instance->denyWrite && self::matchesAddress(Configuration::$instance->denyWrite))
						throw new ApplicationException("あなたのホストからの送信は規制されています", 403);

					if (Configuration::$instance->denyWriteFromMobileWithoutID && Util::canGetMobileUniqueID() && Util::getMobileUniqueID() == null)
						throw new ApplicationException(Util::getMobileUniqueIDName() . "の送信設定を有効にしてください", 403);
				}
			}
			else
			{
				if (!Configuration::$instance->allowRead || !self::matchesAddress(Configuration::$instance->allowRead))
				{
					if ((Configuration::$instance->useBBQ & Configuration::BBQ_READ) && self::isBBQed())
						throw new ApplicationException("公開プロキシを使用した閲覧は規制されています", 403);

					if (Configuration::$instance->denyRead && self::matchesAddress(Configuration::$instance->denyRead))
						throw new ApplicationException("あなたのホストからの閲覧は規制されています", 403);
				}
			}

			self::rewriteHtaccess();
			Util::unencodeInputs();
			self::resolve(Util::getPathInfo());
		}
		catch (Exception $ex)
		{
			Visualizer::statusCode(is_a($ex, "ApplicationException") ? $ex->httpCode : 500);
			Visualizer::noCache();
			Visualizer::$data = $ex;

			if (self::$handlerType == "json" || strstr(Util::getPathInfo(), ".json") == ".json")
				Visualizer::json(array("error" => $ex->getMessage(), "data" => $ex->data));
			else
				Visualizer::visualize("Exception");
		}
	}

	private static function rewriteHtaccess()
	{
		if (Configuration::$instance->htaccessAutoConfig && !trim(Util::getPathInfo(), "/") && is_file($htaccess = ".htaccess") && is_writable($htaccess))
		{
			$base = dirname($_SERVER["SCRIPT_NAME"]);
			$content = file_get_contents($htaccess);
			$newcontent = preg_replace('/(RewriteRule \^\(\.\+\)\$) .*/', '$1 /' . trim($base, "/") . '/' . Util::INDEX_FILE_NAME . '?path=\$1 [QSA]', $content);

			if ($content != $newcontent)
				file_put_contents($htaccess, $newcontent, LOCK_EX);
		}
	}

	/**
	 * @param string $pathInfo
	 * @return mixed
	 */
	static function resolve($pathInfo)
	{
		$pathInfo = explode("/", trim($pathInfo, "/"));

		if ($pathInfo && Util::isEmpty($pathInfo[0]))
			array_shift($pathInfo);

		if ($pathInfo && ($idx = mb_strrpos($pathInfo[count($pathInfo) - 1], ".")) !== false)
		{
			$last = &$pathInfo[count($pathInfo) - 1];
			self::$handlerType = mb_substr($last, $idx + 1);
			$last = mb_substr($last, 0, $idx);
		}

		self::load(HANDLER_DIR . "Index");
		self::$handlerName = "Index";
		self::$handler = new IndexHandler();
		IndexHandler::$instance = &self::$handler;

		$callbackName = self::$actionName = DEFAULT_ACTION;

		foreach (array("", "_") as $i)
			if ($pathInfo)
				if ($pathInfo && is_callable(array(self::$handler, $i . $pathInfo[0])))
					$callbackName = $i . (self::$actionName = array_shift($pathInfo));
				else if (is_callable(array(self::$handler, $i . $pathInfo[count($pathInfo) - 1])))
					$callbackName = $i . (self::$actionName = array_pop($pathInfo));

		self::$pathInfo = $pathInfo;
		$callback = array(self::$handler, $callbackName);

		return call_user_func_array($callback, $pathInfo);
	}

	static function load($name)
	{
		if (is_array($name))
			array_walk($name, array("App", "load"));
		else if (is_file($file = APP_DIR . "{$name}.php"))
			require $file;
		else
			throw new ApplicationException("{$name} not found");
	}

	/**
	 * @param string $name
	 * @param string $action
	 * @return mixed
	 */
	static function callHandler($name, $action, array $args)
	{
		$handlerName = (App::$handlerName = ucfirst($name)) . "Handler";
		self::load(HANDLER_DIR . App::$handlerName);
		self::$handler = new $handlerName;
		eval($handlerName . '::$instance = &self::$handler;');

		return call_user_func_array(array(self::$handler, $action), $args);
	}

	/**
	 * @param string $name
	 * @return PDO
	 */
	static function openDB($name = "data")
	{
		return Configuration::$instance->dataStore->open($name);
	}

	/**
	 * @param bool $vacuum [optional]
	 * @param bool $commitTransaction [optional]
	 */
	static function closeDB(PDO &$db, $vacuum = false)
	{
		return Configuration::$instance->dataStore->close($db, $vacuum);
	}

}

App::$startTime = microtime(true);
App::load(array("Library/simple_html_dom", CORE_DIR . "ApplicationException"));
App::load(CORE_DIR . "Util");
App::precondition();
App::stripMagicQuotesSlashes();
App::load(array(CORE_DIR . "Auth", CORE_DIR . "Configuration", CORE_DIR . "Cookie", CORE_DIR . "DataStore", CORE_DIR . "Handler", CORE_DIR . "SessionStore", CORE_DIR . "Visualizer", MODEL_DIR . "Board", MODEL_DIR . "Comment", MODEL_DIR . "Evaluation", MODEL_DIR . "Meta", MODEL_DIR . "SearchIndex", MODEL_DIR . "SearchIndex/Classic", MODEL_DIR . "SearchIndex/SQLite", MODEL_DIR . "SearchIndex/MySQL", MODEL_DIR . "Statistics", MODEL_DIR . "ThreadEntry", MODEL_DIR . "Thread"));
App::load("../config");

if (!Configuration::$instance->dataStore)
	Configuration::$instance->dataStore = new SQLiteDataStore();

Auth::useSession();
App::main();
?>