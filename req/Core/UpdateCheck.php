<?php
namespace Megalopolis;

/**
 * GitHub のリリースから新しいバージョンが公開されているかを調べる。
 * 問い合わせの結果は 1 日分保存し、失敗した場合も次の問い合わせまで同じだけ待つ
 */
class UpdateCheck
{
	const string RELEASE_API_URL = "https://api.github.com/repos/mfakane/Megalopolis/releases/latest";
	const string RELEASES_URL = "https://github.com/mfakane/Megalopolis/releases/latest";
	const string CACHE_FILE = "updatecheck.php";
	const string CACHE_FILE_HEADER = "<?php exit; ?>\n";
	const int INTERVAL = 86400;
	const int TIMEOUT = 3;

	/**
	 * 現在のバージョンより新しいリリースがあれば返す
	 * @param (callable(): ?string)|null $fetch 最新のリリースの JSON を返す。失敗した場合は null
	 * @return array{version: int, url: string}|null
	 */
	static function getNewerRelease(?callable $fetch = null, string $directory = Constant::DATA_DIR, ?int $now = null): ?array
	{
		$release = self::getLatestRelease($fetch ?? self::fetch(...), $directory, $now ?? time());

		return $release !== null && $release["version"] > App::VERSION ? $release : null;
	}

	/**
	 * 保存してある最新のリリースを返す。保存してから 1 日たっていれば問い合わせ直す
	 * @param callable(): ?string $fetch
	 * @return array{version: int, url: string}|null
	 */
	static function getLatestRelease(callable $fetch, string $directory, int $now): ?array
	{
		$fp = @fopen(rtrim($directory, "/") . "/" . self::CACHE_FILE, "c+");

		if ($fp === false)
			return null;

		try {
			// 同時に開かれても問い合わせるのは 1 回だけにする
			if (!flock($fp, LOCK_EX))
				return null;

			$content = stream_get_contents($fp);
			$cache = $content === false ? null : json_decode(substr($content, strlen(self::CACHE_FILE_HEADER)), true);

			if (!is_array($cache) || !is_int($cache["checkedAt"] ?? null) || $cache["checkedAt"] > $now || $now - $cache["checkedAt"] >= self::INTERVAL) {
				$json = $fetch();
				$release = $json === null ? null : self::parseRelease($json);
				$cache = array("checkedAt" => $now) + ($release ?? array());

				ftruncate($fp, 0);
				rewind($fp);
				fwrite($fp, self::CACHE_FILE_HEADER . (json_encode($cache) ?: "{}"));
				fflush($fp);
			}

			flock($fp, LOCK_UN);
		} finally {
			fclose($fp);
		}

		return is_int($cache["version"] ?? null) && is_string($cache["url"] ?? null)
			? array("version" => $cache["version"], "url" => $cache["url"])
			: null;
	}

	/**
	 * GitHub の API が返した最新のリリースの JSON から、バージョンとリリースのページの URL を取り出す
	 * @return array{version: int, url: string}|null
	 */
	static function parseRelease(string $json): ?array
	{
		$data = json_decode($json, true);

		if (!is_array($data) || !empty($data["draft"]) || !empty($data["prerelease"]))
			return null;

		// r52 の形のタグだけを正式なリリースとして扱い、rc52 などは無視する
		if (!is_string($data["tag_name"] ?? null) || !preg_match('/\A' . preg_quote(App::VERSION_PREFIX, "/") . '(\d+)\z/', $data["tag_name"], $m))
			return null;

		// 受け取った URL はリンクにするため、GitHub のページ以外は使わない
		$url = $data["html_url"] ?? null;

		return array(
			"version" => (int)$m[1],
			"url" => is_string($url) && str_starts_with($url, "https://github.com/") && !preg_match('/[\s"<>]/', $url) ? $url : self::RELEASES_URL,
		);
	}

	/**
	 * GitHub の API に最新のリリースを問い合わせる。応答が無い場合や 200 以外の場合は null を返す
	 */
	static function fetch(): ?string
	{
		$headers = array(
			"User-Agent: " . App::NAME . "/" . App::VERSION_PREFIX . App::VERSION,
			"Accept: application/vnd.github+json",
			"X-GitHub-Api-Version: 2022-11-28",
		);

		if (extension_loaded("curl")) {
			$ch = curl_init(self::RELEASE_API_URL);

			if ($ch === false)
				return null;

			curl_setopt_array($ch, array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
				CURLOPT_TIMEOUT => self::TIMEOUT,
				CURLOPT_HTTPHEADER => $headers,
			));
			$body = curl_exec($ch);
			$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

			return is_string($body) && $status === 200 ? $body : null;
		}

		if (!ini_get("allow_url_fopen"))
			return null;

		$fp = @fopen(self::RELEASE_API_URL, "r", false, stream_context_create(array("http" => array(
			"method" => "GET",
			"header" => implode("\r\n", $headers),
			"timeout" => self::TIMEOUT,
			"follow_location" => 0,
			"ignore_errors" => true,
		))));

		if ($fp === false)
			return null;

		try {
			$meta = stream_get_meta_data($fp);
			$body = stream_get_contents($fp);
			$statusLine = is_array($meta["wrapper_data"] ?? null) ? (string)($meta["wrapper_data"][0] ?? "") : "";

			return is_string($body) && preg_match('#\AHTTP/\S+\s+200\b#', $statusLine) ? $body : null;
		} finally {
			fclose($fp);
		}
	}
}
?>
