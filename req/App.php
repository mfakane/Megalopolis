<?php
class App
{
	const NAME = "Megalopolis";
	const VERSION = 4;
	const MEGALITH_VERSION = 50;
	
	const META_TABLE = "meta";
	const THREAD_ENTRY_TABLE = "threadEntry";
	const THREAD_EVALUATION_TABLE = "threadEvaluation";
	const THREAD_TAG_TABLE = "threadTag";
	const THREAD_TABLE = "thread";
	const THREAD_STYLE_TABLE = "threadStyle";
	const THREAD_PASSWORD_TABLE = "threadPassword";
	const COMMENT_TABLE = "comment";
	const EVALUATION_TABLE = "evaluation";
	const INDEX_TABLE = "searchIndex";
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
			self::precondition(!get_magic_quotes_gpc(), "magic_quotes_gpc disabled");
			self::precondition(in_array(Util::HASH_ALGORITHM, hash_algos()), "hash_algos() " . Util::HASH_ALGORITHM);
			
			mb_internal_encoding("UTF-8");
			mb_http_output("UTF-8");
			
			if (!function_exists("lcfirst"))
			{
				function lcfirst($str)
				{
					$str{0} = strtolower($str{0});
					
					return $str;
				}
			}
		}
		else if (!$cond)
			throw new ApplicationException("Precondition {$desc} failed.");
	}
	
	private static function isBBQed()
	{
		return substr_count($_SERVER["REMOTE_ADDR"], ".") == 3
			&& gethostbyname(implode(".", array_reverse(explode(".", $_SERVER["REMOTE_ADDR"]))) . ".niku.2ch.net") == "127.0.0.2";
	}
	
	private static function isDenied($arr)
	{
		$addr = $_SERVER["REMOTE_ADDR"];
		$host = isset($_SERVER["REMOTE_HOST"]) && $_SERVER["REMOTE_HOST"] ? $_SERVER["REMOTE_HOST"] : gethostbyaddr($addr);
		
		foreach ($arr as $i)
			if (Util::wildcard($i, $addr) ||
				Util::wildcard($i, $host))
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
			if ($_POST)
			{
				if (!isset($_SERVER["HTTP_REFERER"]) || mb_strpos($_SERVER["HTTP_REFERER"], Util::getAbsoluteUrl()) != 0)
					throw new ApplicationException("不正な送信元です", 403);
				
				if ((Configuration::$instance->useBBQ & Configuration::BBQ_WRITE) &&
					self::isBBQed())
					throw new ApplicationException("公開プロキシを使用した送信は規制されています", 403);
				
				if (Configuration::$instance->denyWrite &&
					self::isDenied(Configuration::$instance->denyWrite))
					throw new ApplicationException("あなたのホストからの送信は規制されています", 403);
			}
			else
			{
				if ((Configuration::$instance->useBBQ & Configuration::BBQ_READ) &&
					self::isBBQed())
					throw new ApplicationException("公開プロキシを使用した閲覧は規制されています", 403);
				
				if (Configuration::$instance->denyRead &&
					self::isDenied(Configuration::$instance->denyRead))
					throw new ApplicationException("あなたのホストからの閲覧は規制されています", 403);
			}

			self::rewriteHtaccess();
			self::resolve(Util::getPathInfo());
		}
		catch (Exception $ex)
		{
			Visualizer::statusCode(is_a($ex, "ApplicationException") ? $ex->httpCode : 500);
			Visualizer::$data = $ex;
			
			if (self::$handlerType == "json")
				Visualizer::json(array
				(
					"error" => $ex->getMessage()
				));
			else
				Visualizer::visualize("Exception");
		}
	}
	
	private static function rewriteHtaccess()
	{
		if (Configuration::$instance->htaccessAutoConfig &&
			!trim(Util::getPathInfo(), "/") &&
			is_file($htaccess = ".htaccess") &&
			is_writable($htaccess))
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
		
		if ($pathInfo && empty($pathInfo[0]))
			array_shift($pathInfo);

		if ($pathInfo && mb_strstr($pathInfo[count($pathInfo) - 1], "."))
		{
			$last = &$pathInfo[count($pathInfo) - 1];
			self::$handlerType = mb_substr(mb_strstr($last, "."), 1);
			$last = mb_substr($last, 0, -mb_strlen(self::$handlerType) - 1);
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
	 * @param bool $beginTransaction [optional]
	 * @return PDO
	 */
	static function openDB($name = "data", $beginTransaction = true)
	{
		$db = new PDO(sprintf("sqlite:%s%s.sqlite", DATA_DIR, $name));
		
		if ($beginTransaction)
			$db->beginTransaction();
		
		if ($name == self::INDEX_DATABASE)
			SearchIndex::ensureTable($db);
		else
		{
			Meta::ensureTable($db);
			Board::ensureTable($db);
		}
		
		return $db;
	}
	
	/**
	 * @param bool $vacuum [optional]
	 * @param bool $commitTransaction [optional]
	 */
	static function closeDB(PDO &$db, $vacuum = false, $commitTransaction = true)
	{
		if ($commitTransaction)
			$db->commit();
		
		if ($vacuum)
			$db->exec("vacuum");
		
		$db = null;
	}
}

App::$startTime = microtime(true);
App::load(array
(
	"Library/cssparser",
	"Library/simple_html_dom",
	CORE_DIR . "ApplicationException"
));
App::load(CORE_DIR . "Util");
App::precondition();
App::load(array
(
	CORE_DIR . "Auth",
	CORE_DIR . "Configuration",
	CORE_DIR . "Cookie",
	CORE_DIR . "Handler",
	CORE_DIR . "Visualizer",
	MODEL_DIR . "Board",
	MODEL_DIR . "Comment",
	MODEL_DIR . "Evaluation",
	MODEL_DIR . "Meta",
	MODEL_DIR . "SearchIndex",
	MODEL_DIR . "Statistics",
	MODEL_DIR . "ThreadEntry",
	MODEL_DIR . "Thread"
));
App::load("../config");
App::main();
?>