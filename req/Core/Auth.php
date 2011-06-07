<?php
class Auth
{
	const SESSION_PASSWORD = "Auth::password";
	const SESSION_IS_ADMIN = "Auth::isAdmin";
	const SESSION_FINGERPRINT = "Auth::fingerprint";
	
	static $caption = "認証";
	static $label = "パスワード";
	static $details = null;
	private static $isAdmin = false;
	
	static function useSession()
	{
		if (!self::isSessionEnabled())
		{
			ini_set("session.use_cookies", 1);
			ini_set("session.use_only_cookies", 1);
			ini_set("session.use_trans_sid", 0);
			ini_set("session.cookie_httponly", 1);
			
			session_set_cookie_params(0);
			session_name("MEGALOPOLIS_" . basename(dirname(dirname(dirname(__FILE__)))));
			session_start();
			
			$currentFingerprint = self::createFingerprint();
			
			if (!isset($_SESSION[self::SESSION_FINGERPRINT]) ||
				$_SESSION[self::SESSION_FINGERPRINT] != $currentFingerprint)
				self::logout();

			$_SESSION[self::SESSION_FINGERPRINT] = $currentFingerprint;
		}
	}
	
	private static function createFingerprint()
	{
		return Util::hash(implode(", ", array
		(
			self::getSessionID(),
			$_SERVER["REMOTE_ADDR"],
			isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : null,
			isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? $_SERVER["HTTP_ACCEPT_LANGUAGE"] : null,
			isset($_SERVER["HTTP_ACCEPT_CHARSET"]) ? $$_SERVER["HTTP_ACCEPT_CHARSET"] : null
		)));
	}
	
	static function commitSession()
	{
		if (self::isSessionEnabled())
			session_commit();
	}
	
	static function logout()
	{
		if (!self::isSessionEnabled())
			return;
		
		if (isset($_COOKIE[session_name()]))
			setcookie(session_name(), "", time() - 42000, "/");
		
		self::unsetSession();
		session_destroy();
		self::$isAdmin = false;
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
	
	static function cleanSession()
	{
		if (!self::isSessionEnabled())
			return;
		
		foreach ($_SESSION as $k => $v)
			if (!in_array($k, array(self::SESSION_IS_ADMIN, self::SESSION_PASSWORD, self::SESSION_FINGERPRINT)))
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
	 * @param bool $ensureSessionID [optional]
	 * @return string
	 */
	static function login($admin = false, $ensureSessionID = true)
	{
		if (self::hasSession($admin))
			return $_SESSION[self::SESSION_PASSWORD];
		else if (isset($_POST["password"]))
		{
			if ($ensureSessionID)
				self::ensureSessionID();
			
			self::resetSession();
			$_SESSION[self::SESSION_IS_ADMIN] = $admin;
			
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
		self::unsetSession();
		Visualizer::$data = $error;
		Visualizer::visualize("Auth");
		
		exit;
	}
}

Auth::useSession();
?>