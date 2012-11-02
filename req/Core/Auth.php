<?php
class Auth
{
	const SESSION_PASSWORD = "Auth_password";
	const SESSION_IS_ADMIN = "Auth_isAdmin";
	const SESSION_FINGERPRINT = "Auth_fingerprint";
	const SESSION_TOKEN = "Auth_token";
	
	static $caption = "認証";
	static $label = "パスワード";
	static $details = null;
	private static $isAdmin = false;
	
	static function useSession($begin = false)
	{
		$sessionName = "MEGALOPOLIS_" . basename(dirname(dirname(dirname(__FILE__))));
		
		if (!self::isSessionEnabled() && ($begin || isset($_COOKIE[$sessionName])))
		{
			ini_set("session.use_cookies", 1);
			ini_set("session.use_only_cookies", 1);
			ini_set("session.use_trans_sid", 0);
			ini_set("session.cookie_httponly", 1);
			ini_set("session.gc_probability", 1);
			ini_set("session.gc_divisor", 100);
			ini_set("session.gc_maxlifetime", 1440);
			
			session_cache_limiter(false);
			session_set_cookie_params(0, dirname(Util::getPhpSelf()));
			session_name($sessionName);
			
			if (Configuration::$instance->storeSessionIntoDataStore)
				SessionStore::useSessionStore();
			
			session_start();
			
			$currentFingerprint = self::createFingerprint();
			
			if (!isset($_SESSION[self::SESSION_FINGERPRINT]))
				$_SESSION[self::SESSION_FINGERPRINT] = $currentFingerprint;
			else if ($_SESSION[self::SESSION_FINGERPRINT] != $currentFingerprint)
				self::logout();
		}
	}
	
	private static function createFingerprint()
	{
		return hash(Util::HASH_ALGORITHM, implode(", ", array
		(
			self::getSessionID(),
			$_SERVER["REMOTE_ADDR"],
			isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : null,
			isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? $_SERVER["HTTP_ACCEPT_LANGUAGE"] : null,
			isset($_SERVER["HTTP_ACCEPT_CHARSET"]) ? $_SERVER["HTTP_ACCEPT_CHARSET"] : null
		)));
	}
	
	static function commitSession()
	{
		if (self::isSessionEnabled())
		{
			session_commit();
			
			if (self::hasSession())
				Visualizer::noCache();
		}
	}
	
	static function logout()
	{
		if (!self::isSessionEnabled())
			return;
		
		if (isset($_COOKIE[session_name()]))
			setcookie(session_name(), "", time() - 42000, dirname(Util::getPhpSelf()));
		
		self::unsetSession();
		session_destroy();
		self::$isAdmin = false;
	}
	
	static function hasToken()
	{
		return isset($_SESSION[self::SESSION_TOKEN])
			&& !empty($_SESSION[self::SESSION_TOKEN]);
	}
	
	static function createToken()
	{
		return $_SESSION[self::SESSION_TOKEN] = hash(Util::HASH_ALGORITHM, mt_rand() . self::createFingerprint());
	}
	
	static function clearToken()
	{
		if (isset($_SESSION[self::SESSION_TOKEN]))
			unset($_SESSION[self::SESSION_TOKEN]);
	}
	
	/**
	 * @param string $key [optional]
	 * @param bool $throw [optional]
	 * @return bool
	 */
	static function ensureToken($key = "token", $throw = true)
	{
		$ex = null;
		
		if (!isset($_COOKIE[session_name()]))
			$ex = "セッション ID がセットされていません";
		else if (!isset($_POST[$key]))
			$ex = "遷移情報が無効です";
		else if (!isset($_SESSION[self::SESSION_TOKEN]))
			$ex = "セッションが無効です";
		else if ($_POST[$key] != $_SESSION[self::SESSION_TOKEN])
			$ex = "リクエストが無効です";
		
		if ($ex)
			if ($throw)
				throw new ApplicationException($ex, 403);
			else
				return false;
		
		return true;
	}
	
	static function unsetSession()
	{
		if (self::isSessionEnabled())
		{
			session_unset();
			$_SESSION = array();
			self::$isAdmin = false;
		}
	}
	
	static function resetSession($deleteOld = true)
	{
		if (self::isSessionEnabled())
			session_regenerate_id($deleteOld);
		
		self::$isAdmin = false;
	}
	
	/**
	 * @return bool
	 */
	static function isSessionEnabled()
	{
		return session_id() != "";
	}
	
	/**
	 * @return string
	 */
	static function getSessionID()
	{
		return self::isSessionEnabled() ? session_id() : null;
	}
	
	/**
	 * @param bool $hasAdminOnly
	 * @return bool
	 */
	static function hasSession($hasAdminOnly = false)
	{
		if ($hasAdminOnly && self::$isAdmin)
			return self::$isAdmin;
		else
			return self::isSessionEnabled()
				&& isset($_SESSION[self::SESSION_PASSWORD])
				&& (!$hasAdminOnly || isset($_SESSION[self::SESSION_IS_ADMIN]) && (self::$isAdmin = $_SESSION[self::SESSION_IS_ADMIN] && Util::hashEquals(Configuration::$instance->adminHash, $_SESSION[self::SESSION_PASSWORD])));
	}
	
	static function cleanSession($clearToken = true)
	{
		if (!self::isSessionEnabled())
			return;
		
		foreach ($_SESSION as $k => $v)
			if (!in_array($k, array(self::SESSION_IS_ADMIN, self::SESSION_PASSWORD, self::SESSION_FINGERPRINT, $clearToken ? null : self::SESSION_TOKEN)))
				unset($_SESSION[$k]);
		
		self::$isAdmin = false;
	}
	
	/**
	 * @param string $key [optional]
	 * @param bool $throw [optional]
	 * @return bool
	 */
	static function ensureSessionID($key = "sessionID", $throw = true)
	{
		if (!isset($_POST[$key]) ||
			$_POST[$key] != self::getSessionID())
			if ($throw)
				throw new ApplicationException("不正なリクエストです", 403);
			else
				return false;
		
		return true;
	}
	
	/**
	 * @param bool $hasAdminOnly [optional]
	 * @param bool $ensureToken [optional]
	 * @return string
	 */
	static function login($admin = false, $ensureToken = true)
	{
		self::useSession(true);
		
		if (self::hasSession($admin))
			return $_SESSION[self::SESSION_PASSWORD];
		else if (isset($_POST["password"]))
		{
			if ($ensureToken)
				self::ensureToken();
			
			self::clearToken();
			self::resetSession();
			$_SESSION[self::SESSION_IS_ADMIN] = $admin;
			$_SESSION[self::SESSION_FINGERPRINT] = self::createFingerprint();
			
			return $_SESSION[self::SESSION_PASSWORD] = $_POST["password"];
		}
		else
			self::loginError();
	}
	
	/**
	 * @param string $error [optional]
	 */
	static function loginError($error = null)
	{
		self::cleanSession();
		unset($_SESSION[self::SESSION_PASSWORD]);
		unset($_SESSION[self::SESSION_IS_ADMIN]);
		self::createToken();
		Visualizer::$data = $error;
		Visualizer::noCache();
		
		if (App::$handlerType == "json")
			throw new ApplicationException($error, 401);
		else
			Visualizer::visualize("Auth");
		
		exit;
	}
}
?>