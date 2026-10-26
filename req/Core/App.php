<?php
namespace Megalopolis;

use \Exception;
use \PDO;

class App
{
	const string NAME = "Megalopolis";
	const string VERSION_PREFIX = "r";
	const int VERSION = 49;
	const int MEGALITH_VERSION = 50;

	const string META_TABLE = "meta";
	const string SUBJECT_TABLE = "subject";
	const string THREAD_ENTRY_TABLE = "threadEntry";
	const string THREAD_EVALUATION_TABLE = "threadEvaluation";
	const string THREAD_TAG_TABLE = "threadTag";
	const string THREAD_TABLE = "thread";
	const string THREAD_STYLE_TABLE = "threadStyle";
	const string THREAD_PASSWORD_TABLE = "threadPassword";
	const string COMMENT_TABLE = "comment";
	const string EVALUATION_TABLE = "evaluation";
	const string AUTHOR_TABLE = "author";
	const string TAG_TABLE = "tags";
	const string SESSION_STORE_TABLE = "sessionStore";
	const string INDEX_DATABASE = "search";

	static ?Handler $handler = null;
	static string $handlerName = "Index";
	static string $actionName = "";
	static string $handlerType = "html";
	/** @var string[] */
	static array $pathInfo = array();
	static float $startTime;

	static function precondition(bool $cond = true, ?string $desc = null): void
	{
		if ($desc == null)
		{
			self::precondition(version_compare(PHP_VERSION, "8.4.0", ">="), "PHP 8.4.0");
			self::precondition(extension_loaded("mbstring"), "mbstring");
			self::precondition(extension_loaded("pdo"), "PDO");
			self::precondition(in_array(Util::HASH_ALGORITHM, hash_algos()), "hash_algos() " . Util::HASH_ALGORITHM);

			assert(mb_language("Japanese"));
			mb_internal_encoding("UTF-8");
			assert(mb_http_output("UTF-8"));
			assert(mb_regex_encoding("UTF-8"));
			ignore_user_abort(true);
		}
		else if (!$cond)
			throw new ApplicationException("Precondition {$desc} failed.");
	}

	private static function isBBQed(): bool
	{
		$addr = Util::getRemoteAddress();

		return $addr !== null && substr_count($addr, ".") == 3 && gethostbyname(implode(".", array_reverse(explode(".", $addr))) . ".niku.2ch.net") == "127.0.0.2";
	}

	/**
	 * @param string[] $arr
	 */
	private static function matchesAddress($arr): bool
	{
		$addr = Util::getRemoteAddress();
		$host = Util::getRemoteHost();

		foreach ($arr as $i)
			if ($addr !== null && Util::wildcard($i, $addr) || $host !== null && Util::wildcard($i, $host))
				return true;

		return false;
	}

	static function main(): void
	{
		try
		{
			if (!is_writable(Constant::DATA_DIR))
				throw new ApplicationException(Constant::DATA_DIR . " が書き込み可能ではありません");
			
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
			self::ensureMigrated();
			self::ensureWritable();
			Util::decodeInputs();
			self::resolve(Util::getPathInfo());
		}
		catch (ApplicationException $ex)
		{
			Visualizer::statusCode($ex->httpCode);
			Visualizer::noCache();
			Visualizer::$data = $ex = self::hideInternalError($ex);

			if (self::$handlerType == "json" || strstr(Util::getPathInfo(), ".json") == ".json")
				Visualizer::json(array("error" => $ex->getMessage(), "data" => $ex->data));
			else
				Visualizer::visualize("Exception");
		}
		catch (Exception $ex)
		{
			Visualizer::statusCode(500);
			Visualizer::noCache();
			Visualizer::$data = $ex = self::hideInternalError($ex);

			if (self::$handlerType == "json" || strstr(Util::getPathInfo(), ".json") == ".json")
				Visualizer::json(array("error" => $ex->getMessage(), "data" => null));
			else
				Visualizer::visualize("Exception");
		}
	}

	/**
	 * テーブルの構造の更新が終わるまでは、管理者のログインと更新の再実行のほかはメンテナンス中とする
	 */
	private static function ensureMigrated(): void
	{
		$store = Configuration::$instance->dataStore;
		if (!$store) return;

		$dh = self::openDB();
		$error = $store->isMigrationPending() ? $dh->execute(fn($db) => Migration::getError($db)) : null;
		$dh->close();

		$path = trim(Util::getPathInfo(), "/");

		if (!$store->isMigrationPending() || preg_match('@^(login|logout|util/migrate)(\.\w+)?$@', $path))
			return;

		header("Retry-After: 60");

		// 管理者には、その場で再実行できるようにする
		if (Auth::hasSession(true) && !Auth::hasToken())
			Auth::createToken();

		$ex = new ApplicationException($error === null
			? "データベースの更新処理中のため、現在メンテナンス中です。しばらくしてから再度お試しください"
			: "データベースの更新処理が完了していないため、現在メンテナンス中です。管理者はログインして、更新処理を再実行してください", 503);
		// 失敗の原因は管理者にだけ示す
		$ex->data = array("migration" => true, "error" => Auth::hasSession(true) ? $error : null);

		throw $ex;
	}

	/**
	 * データベースの変換中は、投稿・コメント・評価などの書き込みを受け付けない
	 */
	private static function ensureWritable(): void
	{
		$path = trim(Util::getPathInfo(), "/");
		$writes = $_POST || preg_match('@(^|/)uncomment(\.\w+)?$@', $path);
		// 管理者のログインと変換ツール自身は通す
		$allowed = preg_match('@^(login|logout|util/charset)(\.\w+)?$@', $path);

		if (!$writes || $allowed || !Configuration::$instance->dataStore instanceof MySQLDataStore)
			return;

		$dh = self::openDB();
		$maintenance = Configuration::$instance->dataStore->isMaintenance();
		$dh->close();

		if ($maintenance)
			throw new ApplicationException("データベースの変換中のため、現在は投稿・コメント・評価などの書き込みを停止しています。しばらくしてから再度お試しください", 503);
	}

	/**
	 * データベースのエラーなど内部の詳細は利用者に見せず、ログに残す
	 * @template T of Exception
	 * @param T $ex
	 * @return T|ApplicationException
	 */
	private static function hideInternalError(Exception $ex): Exception
	{
		$internal = !$ex instanceof ApplicationException
			|| ($ex->getPrevious() !== null && !$ex->getPrevious() instanceof ApplicationException);

		if (!$internal || Configuration::$instance->debug)
			return $ex;

		error_log((string)$ex);

		return new ApplicationException("内部エラーが発生しました。しばらくしてから再度お試しください", 500);
	}

	private static function rewriteHtaccess(): void
	{
		if (Configuration::$instance->htaccessAutoConfig && !trim(Util::getPathInfo(), "/") && is_file($htaccess = ".htaccess") && is_writable($htaccess) && isset($_SERVER["SCRIPT_NAME"]))
		{
			$base = dirname($_SERVER["SCRIPT_NAME"]);
			$content = file_get_contents($htaccess);
			if ($content === false) return;
			
			$target = "/" . trim(trim($base, "/") . "/" . Util::INDEX_FILE_NAME, "/");
			$newcontent = (string)preg_replace('/(RewriteRule \^\(\.\+\)\$) .*/', '$1 ' . $target . '?path=\$1 [QSA]', $content);

			if ($content != $newcontent)
				file_put_contents($htaccess, $newcontent, LOCK_EX);
		}
	}

	/**
	 * Public dispatcher: callers may use the handler's return value.
	 * @api
	 * @return mixed
	 */
	static function resolve(string $pathInfo)
	{
		$pathInfo = explode("/", trim($pathInfo, "/"));

		if (isset($pathInfo[0]) && Util::isEmpty($pathInfo[0]))
			array_shift($pathInfo);

		if (isset($pathInfo[0]) && ($idx = mb_strrpos($pathInfo[count($pathInfo) - 1], ".")) !== false)
		{
			$last = $pathInfo[count($pathInfo) - 1];

			self::$handlerType = mb_substr($last, $idx + 1);
			$last = mb_substr($last, 0, $idx);

			$pathInfo[count($pathInfo) - 1] = $last;
		}

		self::load(Constant::HANDLER_DIR . "Index");
		self::$handlerName = "Index";
		self::$handler = new IndexHandler();
		IndexHandler::$instance = self::$handler;

		$callbackName = self::$actionName = Constant::DEFAULT_ACTION;

		// 先頭または末尾の要素でアクションを決める。決まった後の要素 (タグや作者名) はアクションとみなさない
		foreach (array("", "_") as $i)
			if (isset($pathInfo[0]))
				if (self::isAction(self::$handler, $i . $pathInfo[0])) {
					$callbackName = $i . (self::$actionName = array_shift($pathInfo));

					break;
				} else if (self::isAction(self::$handler, $i . $pathInfo[count($pathInfo) - 1])) {
					$callbackName = $i . (self::$actionName = array_pop($pathInfo));

					break;
				}

		self::$pathInfo = $pathInfo;
		$callback = array(self::$handler, $callbackName);

		return call_user_func_array($callback, $pathInfo);
	}
	
	/**
	 * @param string[]|string $name
	 */
	static function load(array|string $name): void
	{
		if (is_array($name))
			array_walk($name, fn(string $x) => self::load($x));
		else if (is_file($file = Constant::APP_DIR . "{$name}.php"))
			require $file;
		else
			throw new ApplicationException("{$name} not found");
	}

	/**
	 * @param string[] $args
	 * @return mixed
	 */
	/**
	 * URL から呼べるのはハンドラの公開されたアクション (インスタンスメソッド) だけにする
	 */
	private static function isAction(Handler $handler, string $name): bool
	{
		if (!method_exists($handler, $name))
			return false;

		$method = new \ReflectionMethod($handler, $name);

		return $method->isPublic() && !$method->isStatic();
	}

	static function callHandler(string $name, string $action, array $args): bool
	{
		$cast = fn(mixed $orig): Handler => $orig;

		$handlerName = "\\Megalopolis\\" . (App::$handlerName = ucfirst($name)) . "Handler";
		self::load(Constant::HANDLER_DIR . App::$handlerName);

		if (class_exists($handlerName))
			self::$handler = $cast(new $handlerName);
		else
			throw new ApplicationException("{$handlerName} not found");

		$handlerName::$instance = self::$handler;

		if (!self::isAction(self::$handler, $action))
			throw new ApplicationException("ファイルが見つかりません", 404);

		return call_user_func_array(array(self::$handler, $action), $args);
	}

	static function openDB(string $name = "data"): DataStoreHandle
	{
		$dataStore = Configuration::$instance->dataStore;
		if (!$dataStore) throw new ApplicationException("データベースが開けません");

		return new DataStoreHandle($dataStore, $dataStore->open($name));
	}
}
