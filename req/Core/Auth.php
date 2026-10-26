<?php
namespace Megalopolis;

class Auth
{
	const string SESSION_PASSWORD = "Auth_password";
	const string SESSION_IS_ADMIN = "Auth_isAdmin";
	const string SESSION_FINGERPRINT = "Auth_fingerprint";
	const string SESSION_TOKEN = "Auth_token";

	static string $caption = "認証";
	static string $label = "パスワード";
	static ?string $details = null;
	/** ログイン済みの管理者に確認だけを求める場合は false にする */
	static bool $passwordRequired = true;
	private static bool $isAdmin = false;

	static function useSession(bool $beginNew = false): void
	{
		$sessionName = "MEGALOPOLIS_" . basename(dirname(dirname(dirname(__FILE__))));

		if (!self::isSessionEnabled() && ($beginNew || isset($_COOKIE[$sessionName]))) {
			// if (Configuration::$instance->storeSessionIntoDataStore)
			// 	SessionStore::useSessionStore();

			if (!session_start([
				"name" => $sessionName,
				"cookie_path" => "/",
				"cookie_httponly" => true,
				"cookie_lifetime" => 0,
				"cookie_samesite" => "lax",
				"use_strict_mode" => true,
			])) {
				self::logout();
				return;
			}

			if ($beginNew) {
				// セッション開始直後にクッキーの明示的設定を行う
				$sessionId = session_id();
				if ($sessionId !== false) {
					self::setSessionCookie($sessionName, $sessionId);
				}
			}

			$currentFingerprint = self::createFingerprint();

			if (!isset($_SESSION[self::SESSION_FINGERPRINT]))
				$_SESSION[self::SESSION_FINGERPRINT] = $currentFingerprint;
			else if ($_SESSION[self::SESSION_FINGERPRINT] != $currentFingerprint) {
				self::logout();
			}
		}
	}

	/**
	 * 接続元の IP アドレスは含めない。IPv6 の一時アドレスや携帯回線、複数の経路を持つプロキシなどでは
	 * 同じブラウザでもリクエストごとに変わり、そのたびにログアウトさせてしまうため
	 */
	private static function createFingerprint(): string
	{
		return hash(Util::HASH_ALGORITHM, implode(", ", array
		(
			self::getSessionID(),
			$_SERVER["HTTP_USER_AGENT"] ?? null,
			$_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? null,
			$_SERVER["HTTP_ACCEPT_CHARSET"] ?? null
		)));
	}

	static function commitSession(): void
	{
		if (self::isSessionEnabled()) {
			session_commit();

			if (self::hasSession())
				Visualizer::noCache();
		}
	}

	static function logout(): void
	{
		$session = self::isSessionEnabled();
		if ($session === false)
			return;

		if (isset($_COOKIE[$session["name"]]))
			self::setSessionCookie($session["name"], "", time() - 42000);

		self::unsetSession();
		session_destroy();
		self::$isAdmin = false;
	}

	static function hasToken(): bool
	{
		return isset($_SESSION[self::SESSION_TOKEN])
			&& !empty($_SESSION[self::SESSION_TOKEN]);
	}

	/** @api Returns the token for callers rendering their own forms. */
	static function createToken(): string
	{
		$token = bin2hex(random_bytes(20));
		$_SESSION[self::SESSION_TOKEN] = $token;
		return $token;
	}

	static function clearToken(): void
	{
		if (isset($_SESSION[self::SESSION_TOKEN])) {
			unset($_SESSION[self::SESSION_TOKEN]);
		}
	}

	/** @api Retains the success return value for custom handlers. */
	static function ensureToken(): bool
	{
		$key = "token";
		$ex = null;

		$sessionName = session_name();

		if (!isset($_COOKIE[$sessionName]))
			$ex = "セッション ID がセットされていません";
		else if (!isset($_POST[$key]))
			$ex = "遷移情報が無効です";
		else if (!isset($_SESSION[self::SESSION_TOKEN]))
			$ex = "セッションが無効です";
		else if ($_POST[$key] != $_SESSION[self::SESSION_TOKEN])
			$ex = "リクエストが無効です";

		if ($ex !== null) {
			throw new ApplicationException($ex, 403);
		}

		return true;
	}

	static function unsetSession(): void
	{
		if (self::isSessionEnabled()) {
			session_unset();
			$_SESSION = array();
			self::$isAdmin = false;
		}
	}

	static function resetSession(bool $deleteOld = true): void
	{
		if (self::isSessionEnabled()) {
			session_regenerate_id($deleteOld);
		}

		self::$isAdmin = false;
	}

	/**
	 * @return array{id: string, name: string}|false
	 */
	static function isSessionEnabled()
	{
		$id = session_id();
		$name = session_name();

		if ($id === false || $name === false || $id == "" || $name == "")
			return false;

		return [
			"id" => $id,
			"name" => $name
		];
	}

	static function getSessionID(): ?string
	{
		$session = self::isSessionEnabled();
		return $session ? $session["id"] : null;
	}

	static function hasSession(bool $hasAdminOnly = false): bool
	{
		if ($hasAdminOnly && self::$isAdmin) {
			return self::$isAdmin;
		} else {
			$result = self::isSessionEnabled()
				&& isset($_SESSION[self::SESSION_PASSWORD])
				&& (!$hasAdminOnly
					|| self::$isAdmin = isset($_SESSION[self::SESSION_IS_ADMIN])
					&& $_SESSION[self::SESSION_IS_ADMIN]
					&& !empty(Configuration::$instance->adminHash ?? "")
					&& Util::hashEquals(Configuration::$instance->adminHash ?? "", $_SESSION[self::SESSION_PASSWORD]) !== false);
			
			return $result;
		}
	}

	static function cleanSession(bool $clearToken = true): void
	{
		if (!self::isSessionEnabled())
			return;

		foreach ($_SESSION as $k => $_)
			if (!in_array($k, array(self::SESSION_IS_ADMIN, self::SESSION_PASSWORD, self::SESSION_FINGERPRINT, $clearToken ? null : self::SESSION_TOKEN)))
				unset($_SESSION[$k]);

		self::$isAdmin = false;
	}

	/** @api Legacy validation helper retained for custom handlers. */
	static function ensureSessionID(string $key = "sessionID", bool $throw = true): bool
	{
		if (
			!isset($_POST[$key]) ||
			$_POST[$key] != self::getSessionID()
		)
			if ($throw)
				throw new ApplicationException("不正なリクエストです", 403);
			else
				return false;

		return true;
	}

	static function login(bool $admin = false, bool $ensureToken = true): string|false
	{
		self::useSession(true);

		if (self::hasSession($admin)) {
			return $_SESSION[self::SESSION_PASSWORD];
		} else if (isset($_POST["password"]) && is_string($_POST["password"])) {
			if ($ensureToken) {
				self::ensureToken();
			}

			// セッション再生成は認証成功が確定してから行う
			// ここではパスワードを一時保存して、呼び出し側で検証後に resetSession() を行う
			$_SESSION[self::SESSION_IS_ADMIN] = $admin;

			return $_POST["password"];
		} else {
			if (!self::hasToken()) self::createToken();
			// JavaScript から呼ぶ処理では入力画面を出せないため、ログインが切れたことなどを伝える
			self::loginError(App::$handlerType != "json" ? "" : ($admin
				? "ログインの有効期限が切れました。ページを開き直してログインしてください"
				: self::$label . "を入力してください"));
			return false;
		}
	}

	/**
	 * 管理者のパスワードを確かめ、パスワードで認証した場合は以降のリクエストのためにセッションに保存する
	 */
	static function loginAsAdmin(string $error = "管理者パスワードが一致しません"): void
	{
		$password = self::login(true);

		if ($password === false || Util::hashEquals(Configuration::$instance->adminHash ?? "", $password) === false)
			self::loginError($error);

		if (!self::hasSession(true))
			self::finalizeLogin($password);
	}

	static function finalizeLogin(string $password): void
	{
		self::clearToken();
		self::resetSession();
		$_SESSION[self::SESSION_PASSWORD] = $password;
		$_SESSION[self::SESSION_FINGERPRINT] = self::createFingerprint();
		self::createToken();
		
		// セッションクッキーを最も基本的な形で設定
		$sessionName = session_name();
		$sessionId = session_id();
		if ($sessionName !== false && $sessionId !== false) {
			self::setSessionCookie($sessionName, $sessionId);
		}
	}

	/**
	 * session_start と同じ属性でセッションのクッキーを送る (省略すると HttpOnly や SameSite が外れる)
	 */
	private static function setSessionCookie(string $name, string $value, int $expires = 0): void
	{
		setcookie($name, $value, array(
			"expires" => $expires,
			"path" => "/",
			"secure" => isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on",
			"httponly" => true,
			"samesite" => "Lax"
		));
	}

	/**
	 * ログイン済みの管理者に、パスワードを求めずに実行してよいかを確認する画面を表示する。
	 * GET だけで削除などが実行されないよう、確認画面から POST させる
	 * @return never
	 */
	static function confirm(): void
	{
		if (!self::hasToken())
			self::createToken();

		self::$passwordRequired = false;
		Visualizer::$data = "";
		Visualizer::noCache();

		if (App::$handlerType == "json")
			throw new ApplicationException("確認のため POST で送信してください", 405);

		Visualizer::visualize("Auth");

		exit;
	}

	/**
	 * @return never
	 */
	static function loginError(string $error = ""): void
	{
		self::cleanSession(false);
		unset($_SESSION[self::SESSION_PASSWORD]);
		unset($_SESSION[self::SESSION_IS_ADMIN]);
		
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
