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

	/**
	 * 管理者のログインを覚えておくクッキーを、署名し直して期限を延ばす間隔 (秒)
	 */
	const int ADMIN_LOGIN_REFRESH = 3600;

	static function useSession(bool $beginNew = false): void
	{
		$login = self::getAdminLogin();

		// トークンを置くため、管理者にはセッションが切れていても新しく始める
		self::startSession($beginNew || $login !== null);

		// 照合に失敗してログアウトした場合は、管理者のログインも取り消されている
		if ($login === null || ($login = self::getAdminLogin()) === null)
			return;

		if (time() - $login["issued"] >= self::ADMIN_LOGIN_REFRESH)
			self::issueAdminLogin($login["nonce"]);

		if (self::isSessionEnabled())
			$_SESSION[self::SESSION_TOKEN] = self::createAdminToken($login["nonce"]);
	}

	private static function startSession(bool $beginNew): void
	{
		$sessionName = self::cookieName("MEGALOPOLIS_");

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
				self::logoutAdmin();
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
	}

	static function logoutAdmin(): void
	{
		self::clearAdminLogin();
		self::logout();
	}

	/**
	 * @param non-empty-string $prefix
	 * @return non-empty-string
	 */
	private static function cookieName(string $prefix): string
	{
		return $prefix . basename(dirname(dirname(dirname(__FILE__))));
	}

	/** 管理者のログインを覚えておくクッキーの署名に使う鍵 (リクエストごとに求める) */
	private static ?string $adminKey = null;

	/**
	 * 署名の鍵は設定ファイルの内容から作る。adminHash を Util::hash() で書いた設定では値がリクエストごとに
	 * 変わるため使えない。設定ファイルを書き換える (管理者パスワードを変える) と、それまでのログインは無効になる
	 */
	private static function getAdminKey(): ?string
	{
		if ((Configuration::$instance->adminHash ?? "") === "")
			return null;

		if (self::$adminKey === null && ($config = hash_file("sha256", Constant::APP_DIR . "../config.php")) !== false)
			self::$adminKey = hash_hmac("sha256", "Megalopolis admin login", $config);

		return self::$adminKey;
	}

	/**
	 * 署名を確かめた管理者のログイン。期限は、ブラウザを閉じるまでの設定でもサーバー側で区切る
	 * @return array{issued: int, nonce: string}|null
	 */
	private static function getAdminLogin(): ?array
	{
		$value = $_COOKIE[self::cookieName("MEGALOPOLIS_ADMIN_")] ?? null;
		$key = self::getAdminKey();

		if (!is_string($value) || $key === null || !preg_match('/^(\d+)\.([0-9a-f]{32})\.([0-9a-f]{64})$/', $value, $m))
			return null;

		$issued = (int)$m[1];

		if (!hash_equals((string)self::createAdminLogin($issued, $m[2]), $value)
			|| $issued > time() + 60
			|| time() - $issued >= max(Configuration::$instance->adminLoginDays, 1) * 86400)
			return null;

		return array("issued" => $issued, "nonce" => $m[2]);
	}

	private static function createAdminLogin(int $issued, string $nonce): ?string
	{
		$key = self::getAdminKey();

		return $key === null ? null : "{$issued}.{$nonce}." . hash_hmac("sha256", "{$issued}.{$nonce}", $key);
	}

	private static function issueAdminLogin(string $nonce): void
	{
		$issued = time();

		if (($value = self::createAdminLogin($issued, $nonce)) === null)
			return;

		$name = self::cookieName("MEGALOPOLIS_ADMIN_");
		$days = Configuration::$instance->adminLoginDays;
		$_COOKIE[$name] = $value;
		self::setSessionCookie($name, $value, $days > 0 ? $issued + $days * 86400 : 0);
	}

	private static function clearAdminLogin(): void
	{
		$name = self::cookieName("MEGALOPOLIS_ADMIN_");

		if (isset($_COOKIE[$name]))
			self::setSessionCookie($name, "", time() - 42000);

		unset($_COOKIE[$name]);
	}

	/**
	 * 管理者のフォームのトークンはログインから求め、セッションが切れた後に開いたままのページから送っても通るようにする
	 */
	private static function createAdminToken(string $nonce): string
	{
		return substr(hash_hmac("sha256", "token\0" . $nonce, (string)self::getAdminKey()), 0, 40);
	}

	static function hasToken(): bool
	{
		return isset($_SESSION[self::SESSION_TOKEN])
			&& !empty($_SESSION[self::SESSION_TOKEN]);
	}

	/** @api Returns the token for callers rendering their own forms. */
	static function createToken(): string
	{
		$login = self::getAdminLogin();
		$token = $login !== null ? self::createAdminToken($login["nonce"]) : bin2hex(random_bytes(20));
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

		if (($login = self::getAdminLogin()) !== null && isset($_POST[$key]) && is_string($_POST[$key])
			&& hash_equals(self::createAdminToken($login["nonce"]), $_POST[$key]))
			return true;

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
		}
	}

	static function resetSession(bool $deleteOld = true): void
	{
		if (self::isSessionEnabled()) {
			session_regenerate_id($deleteOld);
		}
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

	/**
	 * 管理者としてログインしているか。ログインするのは管理者だけのため、$hasAdminOnly によらない
	 * @psalm-suppress PossiblyUnusedParam 管理者かどうかを区別していた頃の呼び出しを残している
	 */
	static function hasSession(bool $hasAdminOnly = false): bool
	{
		return self::getAdminLogin() !== null;
	}

	static function cleanSession(bool $clearToken = true): void
	{
		if (!self::isSessionEnabled())
			return;

		foreach ($_SESSION as $k => $_)
			if (!in_array($k, array(self::SESSION_IS_ADMIN, self::SESSION_PASSWORD, self::SESSION_FINGERPRINT, $clearToken ? null : self::SESSION_TOKEN)))
				unset($_SESSION[$k]);
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

		// ログイン済みの管理者は、呼び出し側で先に hasSession(true) を確かめる
		if (isset($_POST["password"]) && is_string($_POST["password"])) {
			if ($ensureToken) {
				self::ensureToken();
			}

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
	 * 管理者のパスワードを確かめ、パスワードで認証した場合は以降のリクエストのためにログインを覚えておく
	 */
	static function loginAsAdmin(string $error = "管理者パスワードが一致しません"): void
	{
		if (self::hasSession(true))
			return;

		$password = self::login(true);

		if ($password === false || Util::hashEquals(Configuration::$instance->adminHash ?? "", $password) === false)
			self::loginError($error);

		self::finalizeLogin();
	}

	/**
	 * 管理者のログインを、セッションとは別の署名したクッキーで覚えておく。
	 * セッションはサーバーの設定によっては短い時間で消えるうえ、パスワードを置いておくと漏れたときに危ないため
	 */
	static function finalizeLogin(): void
	{
		self::clearToken();
		self::resetSession();
		unset($_SESSION[self::SESSION_PASSWORD], $_SESSION[self::SESSION_IS_ADMIN]);
		$_SESSION[self::SESSION_FINGERPRINT] = self::createFingerprint();
		self::issueAdminLogin(bin2hex(random_bytes(16)));
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
