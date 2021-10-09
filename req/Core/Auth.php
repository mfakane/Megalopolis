<?php
class Auth
{
	const SESSION_PASSWORD = "Auth_password";
	const SESSION_IS_ADMIN = "Auth_isAdmin";
	const SESSION_FINGERPRINT = "Auth_fingerprint";
	const SESSION_TOKEN = "Auth_token";
	
	static string $caption = "認証";
	static string $label = "パスワード";
	static ?string $details = null;
	private static bool $isAdmin = false;
	
	static function useSession(bool $beginNew = false): void
	{
		$sessionName = "MEGALOPOLIS_" . basename(dirname(dirname(dirname(__FILE__))));
		
		if (!self::isSessionEnabled() && ($beginNew || isset($_COOKIE[$sessionName])))
		{
			session_set_cookie_params(0, dirname(Util::getPhpSelf()), httponly: true);
			session_name($sessionName);
			
			if (Configuration::$instance->storeSessionIntoDataStore)
				SessionStore::useSessionStore();
			
			if (!session_start()) {
				self::logout();
				return;
			}
			
			$currentFingerprint = self::createFingerprint();
			
			if (!isset($_SESSION[self::SESSION_FINGERPRINT]))
				$_SESSION[self::SESSION_FINGERPRINT] = $currentFingerprint;
			else if ($_SESSION[self::SESSION_FINGERPRINT] != $currentFingerprint)
				self::logout();
		}
	}
	
	private static function createFingerprint(): string
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
	
	static function commitSession(): void
	{
		if (self::isSessionEnabled())
		{
			session_commit();
			
			if (self::hasSession())
				Visualizer::noCache();
		}
	}
	
	static function logout(): void
	{
		if (!self::isSessionEnabled())
			return;
		
		if (isset($_COOKIE[session_name()]))
			setcookie(session_name(), "", time() - 42000, dirname(Util::getPhpSelf()));
		
		self::unsetSession();
		session_destroy();
		self::$isAdmin = false;
	}
	
	static function hasToken(): bool
	{
		return isset($_SESSION[self::SESSION_TOKEN])
			&& !empty($_SESSION[self::SESSION_TOKEN]);
	}
	
	static function createToken(): string
	{
		return $_SESSION[self::SESSION_TOKEN] = hash(Util::HASH_ALGORITHM, mt_rand() . self::createFingerprint());
	}
	
	static function clearToken(): void
	{
		if (isset($_SESSION[self::SESSION_TOKEN]))
			unset($_SESSION[self::SESSION_TOKEN]);
	}
	
	static function ensureToken(string $key = "token", bool $throw = true): bool
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
	
	static function unsetSession(): void
	{
		if (self::isSessionEnabled())
		{
			session_unset();
			$_SESSION = array();
			self::$isAdmin = false;
		}
	}
	
	static function resetSession(bool $deleteOld = true): void
	{
		if (self::isSessionEnabled())
			session_regenerate_id($deleteOld);
		
		self::$isAdmin = false;
	}
	
	static function isSessionEnabled(): bool
	{
		return session_id() != "";
	}
	
	static function getSessionID(): ?string
	{
		return self::isSessionEnabled() ? session_id() : null;
	}
	
	static function hasSession(bool $hasAdminOnly = false): bool
	{
		if ($hasAdminOnly && self::$isAdmin)
			return self::$isAdmin;
		else
			return self::isSessionEnabled()
				&& isset($_SESSION[self::SESSION_PASSWORD])
				&& (!$hasAdminOnly
					|| self::$isAdmin = isset($_SESSION[self::SESSION_IS_ADMIN])
						&& $_SESSION[self::SESSION_IS_ADMIN]
						&& !empty(Configuration::$instance->adminHash)
						&& Util::hashEquals(Configuration::$instance->adminHash, $_SESSION[self::SESSION_PASSWORD]));
	}
	
	static function cleanSession(bool $clearToken = true): void
	{
		if (!self::isSessionEnabled())
			return;
		
		foreach ($_SESSION as $k => $v)
			if (!in_array($k, array(self::SESSION_IS_ADMIN, self::SESSION_PASSWORD, self::SESSION_FINGERPRINT, $clearToken ? null : self::SESSION_TOKEN)))
				unset($_SESSION[$k]);
		
		self::$isAdmin = false;
	}
	
	static function ensureSessionID(string $key = "sessionID", bool $throw = true): bool
	{
		if (!isset($_POST[$key]) ||
			$_POST[$key] != self::getSessionID())
			if ($throw)
				throw new ApplicationException("不正なリクエストです", 403);
			else
				return false;
		
		return true;
	}
	
	static function login(bool $admin = false, bool $ensureToken = true): string|false
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
		{
			self::loginError();
			return false;
		}
	}
	
	static function loginError(string $error = ""): void
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
