<?php
namespace Megalopolis;

use \Exception;
use \PDO;
use \PDOStatement;

class Util
{
	const HASH_ALGORITHM = "sha384";
	const HASH_TYPE_ANTHOLOGYS = "Anthologys";
	const HASH_TYPE_MEGALITH = "Megalith";
	const HASH_TYPE_MEGALOPOLIS1 = "Megalopolis1";
	const HASH_TYPE_LATEST = self::HASH_TYPE_MEGALOPOLIS1;
	const PATH_INFO_QUERY_PARAM = "path";
	const INDEX_FILE_NAME = "index.php";
	
	const BROWSER_TYPE_UNKNOWN = "Unknown";
	const BROWSER_TYPE_IPHONE = "iPhone";
	const BROWSER_TYPE_IPAD = "iPad";
	const BROWSER_TYPE_ANDROID = "Android";
	const BROWSER_TYPE_ANDROIDMOBILE = "Android Mobile";
	const BROWSER_TYPE_PSP = "PlayStation Portable";
	const BROWSER_TYPE_3DS = "Nintendo 3DS";
	const BROWSER_TYPE_NETFRONT = "NetFront";
	const BROWSER_TYPE_IEMOBILE7 = "Trident/3.1";
	const BROWSER_TYPE_MOBILE = "Mobile";
	const BROWSER_TYPE_MSIE_NEW = "Trident/";
	const BROWSER_TYPE_MSIE7 = "MSIE 7";
	const BROWSER_TYPE_MSIE6 = "MSIE 6";
	const BROWSER_TYPE_MSIE = "MSIE ";
	const BROWSER_TYPE_FIREFOX2 = "Firefox/2.";
	const BROWSER_TYPE_FIREFOX = "Firefox";
	const BROWSER_TYPE_GECKO = "Gecko";
	const BROWSER_TYPE_PRESTO = "Presto";
	const BROWSER_TYPE_PRESTO_M = "Opera Mobi";
	const BROWSER_TYPE_WEBKIT = "WebKit";
	const BROWSER_TYPE_KHTML = "KHTML";
	
	const MOBILE_TYPE_UNKNOWN = "Unknown";
	const MOBILE_TYPE_IMODE = "docomo";
	const MOBILE_TYPE_EZWEB = "kddi";
	const MOBILE_TYPE_YKEITAI = "softbank";
	const MOBILE_TYPE_WILLCOM = "willcom";
	const MOBILE_TYPE_EMNET = "emobile";
	
	static function getBrowserType(): string
	{
		static $browserType;
		
		if ($browserType)
			return $browserType;
		
		if (!isset($_SERVER["HTTP_USER_AGENT"]))
			return $browserType = self::BROWSER_TYPE_UNKNOWN;
		
		$ua = $_SERVER["HTTP_USER_AGENT"];
		
		foreach (array
		(
			self::BROWSER_TYPE_IPHONE		=> self::BROWSER_TYPE_IPHONE,
			"iPod"							=> self::BROWSER_TYPE_IPHONE,
			self::BROWSER_TYPE_IPAD			=> self::BROWSER_TYPE_IPAD,
			self::BROWSER_TYPE_ANDROID		=> self::BROWSER_TYPE_ANDROID,
			self::BROWSER_TYPE_PSP			=> self::BROWSER_TYPE_PSP,
			self::BROWSER_TYPE_3DS			=> self::BROWSER_TYPE_3DS,
			self::BROWSER_TYPE_NETFRONT		=> self::BROWSER_TYPE_NETFRONT,
			self::BROWSER_TYPE_IEMOBILE7	=> self::BROWSER_TYPE_IEMOBILE7,
			"DoCoMo"						=> self::BROWSER_TYPE_MOBILE,
			"KDDI-"							=> self::BROWSER_TYPE_MOBILE,
			"J-PHONE"						=> self::BROWSER_TYPE_MOBILE,
			"Vodafone"						=> self::BROWSER_TYPE_MOBILE,
			"SoftBank"						=> self::BROWSER_TYPE_MOBILE,
			"DDIPOCKET"						=> self::BROWSER_TYPE_MOBILE,
			"PDXGW"							=> self::BROWSER_TYPE_MOBILE,
			"WILLCOM"						=> self::BROWSER_TYPE_MOBILE,
			"emobile"						=> self::BROWSER_TYPE_MOBILE,
			"Huawei"						=> self::BROWSER_TYPE_MOBILE,
			"IEMobile"						=> self::BROWSER_TYPE_MOBILE,
			self::BROWSER_TYPE_MSIE_NEW		=> self::BROWSER_TYPE_MSIE_NEW,
			self::BROWSER_TYPE_MSIE7		=> self::BROWSER_TYPE_MSIE7,
			self::BROWSER_TYPE_MSIE6		=> self::BROWSER_TYPE_MSIE6,
			self::BROWSER_TYPE_MSIE			=> self::BROWSER_TYPE_MSIE,
			self::BROWSER_TYPE_FIREFOX2		=> self::BROWSER_TYPE_FIREFOX2,
			self::BROWSER_TYPE_FIREFOX		=> self::BROWSER_TYPE_FIREFOX,
			self::BROWSER_TYPE_PRESTO_M		=> self::BROWSER_TYPE_PRESTO_M,
			self::BROWSER_TYPE_PRESTO		=> self::BROWSER_TYPE_PRESTO,
			"Opera"							=> self::BROWSER_TYPE_PRESTO,
			self::BROWSER_TYPE_WEBKIT		=> self::BROWSER_TYPE_WEBKIT,
			self::BROWSER_TYPE_KHTML		=> self::BROWSER_TYPE_KHTML,
			self::BROWSER_TYPE_GECKO		=> self::BROWSER_TYPE_GECKO,
		) as $k => $v)
			if (mb_strstr($ua, $k))
				if ($k == self::BROWSER_TYPE_ANDROID && mb_strstr($ua, self::BROWSER_TYPE_MOBILE))
					return $browserType = self::BROWSER_TYPE_ANDROIDMOBILE;
				else if ($k == self::BROWSER_TYPE_IPHONE && !mb_strstr($ua, "Safari"))
					return $browserType = self::BROWSER_TYPE_MOBILE;
				else
					return $browserType = $v;
		
		return $browserType = self::BROWSER_TYPE_UNKNOWN;
	}
	
	static function getMobileType(): string
	{
		static $mobileType;
		
		if ($mobileType)
			return $mobileType;
		
		$host = isset($_SERVER["REMOTE_HOST"]) && $_SERVER["REMOTE_HOST"] ? $_SERVER["REMOTE_HOST"] : (isset($_SERVER["REMOTE_ADDR"]) ? gethostbyaddr($_SERVER["REMOTE_ADDR"]) : "");
		
		foreach (array
		(
			'\.docomo\.ne\.jp'								=> self::MOBILE_TYPE_IMODE,
			'\.ezweb\.ne\.jp'								=> self::MOBILE_TYPE_EZWEB,
			'\.jp-.\.ne\.jp'								=> self::MOBILE_TYPE_YKEITAI,
			'\.prin\.ne\.jp'								=> self::MOBILE_TYPE_WILLCOM,
			'(\.emnet\.ne\.jp|\.pool\.e-mobile\.ad\.jp)'	=> self::MOBILE_TYPE_EMNET,
		) as $k => $v)
			if (preg_match("/{$k}$/i", $host))
				return $mobileType = $v;
		
		return $mobileType = self::MOBILE_TYPE_UNKNOWN;
	}
	
	static function withMobileUniqueIDRequestSuffix(string $path = ""): string
	{
		return $path . (self::getMobileType() == self::MOBILE_TYPE_IMODE ? (strpos($path, "?") !== false ? "&guid=ON" : "?guid=ON") : "");
	}
	
	static function getMobileUniqueIDName(): string
	{
		$type = self::getMobileType();
		$names = array
		(
			self::MOBILE_TYPE_IMODE		=> "iモード ID ",
			self::MOBILE_TYPE_EZWEB		=> "EZ 番号",
			self::MOBILE_TYPE_YKEITAI	=> "端末シリアル番号",
			self::MOBILE_TYPE_EMNET		=> "EMnet ユーザ ID ",
		);
		
		return $names[$type] ?? "契約者固有 ID";
	}
	
	static function canGetMobileUniqueID(): bool
	{
		return !in_array(self::getMobileType(), array(self::MOBILE_TYPE_UNKNOWN, self::MOBILE_TYPE_WILLCOM));
	}
	
	static function getMobileUniqueID(): ?string
	{
		$header = array
		(
			self::MOBILE_TYPE_IMODE		=> "HTTP_X_DCMGUID",
			self::MOBILE_TYPE_EZWEB		=> "HTTP_X_UP_SUBNO",
			self::MOBILE_TYPE_YKEITAI	=> "HTTP_X_JPHONE_UID",
			self::MOBILE_TYPE_EMNET		=> "HTTP_X_EM_UID",
		);
		$type = self::getMobileType();
		
		if (isset($header[$type]) && isset($_SERVER[$header[$type]]))
			return $type . ":" . $_SERVER[$header[$type]];
		else
			return null;
	}
	
	static function remoteHostMatches(?string $host): bool
	{
		if (isset($_SERVER["REMOTE_ADDR"]) && $_SERVER["REMOTE_ADDR"] == $host)
			return true;

		return self::getRemoteHost() == $host;
	}
	
	static function getRemoteHost(): string|false
	{
		if (self::canGetMobileUniqueID() && $id = self::getMobileUniqueID())
			return $id;
		
		return isset($_SERVER["REMOTE_HOST"]) && $_SERVER["REMOTE_HOST"] ? $_SERVER["REMOTE_HOST"] : (isset($_SERVER["REMOTE_ADDR"]) ? gethostbyaddr($_SERVER["REMOTE_ADDR"]) : "");
	}
	
	static function isCachedByBrowser(?int $lastModified = null, ?string $eTagSeed = null): bool
	{
		$eTag = md5(implode("_", array
		(
			App::VERSION,
			self::getIncludedFilesLastModified(),
			$lastModified,
			isset($_COOKIE[Visualizer::MODE_COOKIE_NAME]) ? $_COOKIE[Visualizer::MODE_COOKIE_NAME] : "auto",
			$eTagSeed
		)));
		
		if (isset($lastModified))
			header("Last-Modified: " . gmdate("D, d M Y H:i:s T", $lastModified));
		
		header("ETag: W/\"" . $eTag . "\"");
		header("Expires: Thu, 19 Nov 1981 08:52:00 GMT");
		header("Cache-Control: private, max-age=0, pre-check=0, must-revalidate");
		
		return !Auth::hasSession()
			&& (!isset($lastModified) || isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) && strtotime($_SERVER["HTTP_IF_MODIFIED_SINCE"]) >= $lastModified)
			&& (isset($_SERVER["HTTP_IF_NONE_MATCH"]) && (trim(substr($_SERVER["HTTP_IF_NONE_MATCH"], strpos($_SERVER["HTTP_IF_NONE_MATCH"], "W/") === 0 ? 2 : 0), '"')) == $eTag);
	}
	
	static function getIncludedFilesLastModified(): int
	{
		return array_reduce(array_map(fn(string $_): int => filemtime($_), get_included_files()), fn(int $x, int $y): int => max($x, $y), 0);
	}
	
	static function getAbsoluteUrl(string $path = ""): string
	{
		static $absoluteUrls = array();
		
		if (isset($absoluteUrls[$path]))
			return $absoluteUrls[$path];
		
		$script = $_SERVER["SCRIPT_NAME"] ?? "/";
		$linkType = Configuration::$instance->linkType;

		if ($linkType == Configuration::LINK_REWRITE ||
			$linkType == Configuration::LINK_AUTO &&
			is_file(".htaccess") &&
			mb_strstr(file_get_contents(".htaccess"), Util::INDEX_FILE_NAME))
			$script = mb_substr($script, 0, -mb_strlen(Util::INDEX_FILE_NAME));
		else if (!Util::isEmpty($path) && !is_file($path))
			if ($linkType == Configuration::LINK_PATH || /* PATH_INFO available */ $linkType == Configuration::LINK_AUTO)
				$script .= "/";
			else
				$script .= "?" . self::PATH_INFO_QUERY_PARAM . "=";
		
		return $absoluteUrls[$path] = "http://" . (($_SERVER["HTTP_HOST"] ?? $_SERVER["SERVER_NAME"] ?? "") . (!isset($_SERVER["SERVER_PORT"]) || $_SERVER["SERVER_PORT"] == 80 ? "" : ":" . $_SERVER["SERVER_PORT"])) . $script . $path;
	}
	
	static function getSuffix(): string
	{
		static $suffix;
		
		if ($suffix)
			return $suffix;
		
		$linkType = Configuration::$instance->linkType;

		if ($linkType == Configuration::LINK_REWRITE ||
			$linkType == Configuration::LINK_AUTO &&
			is_file(".htaccess") &&
			mb_strstr(file_get_contents(".htaccess"), Util::INDEX_FILE_NAME))
			$suffix = "";
		else if ($linkType == Configuration::LINK_PATH || /* PATH_INFO available */ $linkType == Configuration::LINK_AUTO)
			$suffix = Util::INDEX_FILE_NAME . "/";
		else
			$suffix = "?" . self::PATH_INFO_QUERY_PARAM . "=";
		
		return $suffix;
	}
	
	static function getPhpSelf(): string
	{
		static $phpSelf;
		
		if (isset($phpSelf)) return $phpSelf;
		if (isset($_SERVER["PHP_SELF"])) return $phpSelf = self::escapeInput($_SERVER["PHP_SELF"]);
		
		return "/" . self::INDEX_FILE_NAME;
	}
	
	static function getPathInfo(): string
	{
		static $pathInfo;
		
		// TODO: HTTP_X_REWRITE_URL 後で調べる
		
		if ($pathInfo)
			return $pathInfo;
		else if (isset($_GET[self::PATH_INFO_QUERY_PARAM]) && is_string($_GET[self::PATH_INFO_QUERY_PARAM]))
		{
			$pathInfo = self::escapeInput($_GET[self::PATH_INFO_QUERY_PARAM]);
			unset($_GET[self::PATH_INFO_QUERY_PARAM]);
			
			return $pathInfo;
		}
		else if ($rt = self::escapeInput((string)getenv("PATH_INFO")))
			return $pathInfo = $rt;
		else
			return $pathInfo = mb_substr(mb_strstr(self::getPhpSelf(), self::INDEX_FILE_NAME), mb_strlen(self::INDEX_FILE_NAME));
	}
	
	/** @return string[] */
	static function splitTags(string $tags): array
	{
		return array_unique(preg_split("/ +/", mb_convert_kana(trim($tags), "s"), -1, PREG_SPLIT_NO_EMPTY));
	}
	
	static function ensureStatement(PDO $db, PDOStatement $st): ?PDOStatement
	{
		return Configuration::$instance->dataStore?->ensureStatement($db, $st);
	}
	
	static function executeStatement(?PDOStatement $st, ?array $params = null, bool $throw = true): bool
	{
		return Configuration::$instance->dataStore?->executeStatement($st, $params, $throw) ?? false;
	}
	
	static function createTableIfNotExists(PDO $db, array $schema, string $name, ?array $index = null): bool
	{
		return Configuration::$instance->dataStore?->createTableIfNotExists($db, $schema, $name, $index) ?? false;
	}
	
	static function createFullTextTableIfNotExists(PDO $db, array $schema, string $name, string $indexSuffix = "Index"): bool
	{
		return Configuration::$instance->dataStore?->createFullTextTableIfNotExists($db, $schema, $name, $indexSuffix) ?? false;
	}
	
	static function saveToTable(PDO $db, mixed $obj, array $schema, string $name): void
	{
		Configuration::$instance->dataStore?->saveToTable($db, $obj, $schema, $name);
	}
	
	static function hasTable(PDO $db, string $name): bool
	{
		return Configuration::$instance->dataStore?->hasTable($db, $name) ?? false;
	}
	
	static function bindValues(PDOStatement $st, mixed $obj, array $schema): void
	{
		Configuration::$instance->dataStore?->bindValues($st, $obj, $schema);
	}
	
	static function hash(string $raw, int $version = 1, ?string $salt = null, ?int $stretch = null): string
	{
		switch ($version)
		{
			case 1:
				if (!isset($salt))
					$salt = mb_substr(hash(self::HASH_ALGORITHM, (string)mt_rand()), 4, 16);
				
				if (!isset($stretch))
					$stretch = mt_rand(10, 1000);
				
				if (!self::isLength($salt, 16))
					throw new ApplicationException("salt must be 16 chars");
				
				$rt = $raw;
				
				foreach (range(1, $stretch) as $i)
					$rt = "{$version}" . sprintf("%04d", $stretch) . hash(self::HASH_ALGORITHM, $rt) . "{$salt}";
				
				return $rt;
			default:
				throw new ApplicationException("Hash version {$version} not supported");
		}
	}

	static function hashEquals(string $hash, string|false $raw): bool|string
	{
		if ($raw == false) return false;
		
		try
		{
			if (empty($hash))
				return empty($raw);
			else if (self::isLength($hash, 13) && crypt($raw, mb_substr($hash, 0, 2)) == $hash)
				return self::HASH_TYPE_ANTHOLOGYS;
			else if (self::isLength($hash, 40) == 40 && sha1($raw) == $hash)
				return self::HASH_TYPE_MEGALITH;
			else if (mb_substr($hash, 0, 1) == 1 && self::hash($raw, 1, mb_substr($hash, -16), intval(mb_substr($hash, 1, 4))) == $hash)
				return self::HASH_TYPE_MEGALOPOLIS1;
			else
				return false;
		}
		catch (Exception $ex)
		{
			return false;
		}
	}
	
	static function escapeInput(string $input, bool $stripLinebreaks = false): string
	{
		$input = mb_convert_encoding($input, "UTF-8", "UTF-8,Windows-31J,eucJP-win");
		$input = self::escapeAmpersand($input);
		$input = self::decodeNumericEntity($input);
		$input = self::escapeControlChars($input);
		
		if ($stripLinebreaks)
			$input = strtr($input, array("\r" => "", "\n" => ""));
		else
			$input = strtr(strtr($input, array("\r\n" => "\n", "\r" => "\n")), array("\n" => "\r\n"));
		
		$input = mb_convert_encoding($input, "UTF-8");
		
		return $input;
	}
	
	static function decodeInputs(): void
	{
		if (isset($_POST["encoded"]))
		{
			if ($_POST["encoded"] == "true")
			{
				$except = isset($_POST["encodedExcept"]) && is_string($_POST["encodedExcept"]) ? array_flip(explode(",", self::escapeInput($_POST["encodedExcept"]))) : array();
				$except += array("encoded" => true, "encodedExcept" => true);
			
				foreach ($_POST as $k => $v)
					if (!isset($except[$k]) && is_string($v))
						if (($_POST[$k] = base64_decode($v, true)) === false)
							throw new ApplicationException("パラメータ {$k} のデコードに失敗しました", 404);
			}
			
			unset($_POST["encoded"]);
			
			if (isset($_POST["encodedExcept"]))
				unset($_POST["encodedExcept"]);
		}
	}
	
	static function encodeForOutput(string $s): string
	{
		return base64_encode($s);
	}
	
	static function wildcard(string $pattern, string $string): int|false
	{
		return preg_match("/^" . strtr(preg_quote($pattern, "/"), array
		(
			'\*'	=> '.*',
			'\?'	=> '.',
			'\[\!'	=> '[^',
			'\['	=> '[',
			'\]'	=> ']',
			'\.'	=> '\.',
		)) . "$/i", $string);
	}
	
	private static function escapeControlChars(string $input): string
	{
		return preg_replace('@[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]@', " ", $input);
	}
	
	private static function decodeNumericEntity(string $str, ?string $encoding = null): string
	{
		$result = preg_replace_callback("/&#x([\\dA-F]+);?/i", fn(array $matches) => "&#". intval($matches[1], 16) . ";", $str);
		$result = preg_replace("/(&#\\d+);?/", "\\1;", $result);
		$convmap = array(0x000020, 0x000020, 0, 0xffffff,
						  0x000028, 0x000029, 0, 0xffffff,
						  0x000030, 0x00003a, 0, 0xffffff,
						  0x000041, 0x00005a, 0, 0xffffff,
						  0x00005c, 0x00005c, 0, 0xffffff,
						  0x000061, 0x00007a, 0, 0xffffff);
		$result = mb_decode_numericentity($result, $convmap, $encoding);

		return $result;
	}
	
	private static function escapeAmpersand(string $str, bool $is_xhtml = true): string
	{
		$entities = array('AElig', 'Aacute', 'Acirc', 'Agrave', 'Alpha',
						  'Aring', 'Atilde', 'Auml', 'Beta', 'Ccedil',
						  'Chi', 'Dagger', 'Delta', 'ETH', 'Eacute',
						  'Ecirc', 'Egrave', 'Epsilon', 'Eta', 'Euml',
						  'Gamma', 'Iacute', 'Icirc', 'Igrave', 'Iota',
						  'Iuml', 'Kappa', 'Lambda', 'Mu', 'Ntilde',
						  'Nu', 'OElig', 'Oacute', 'Ocirc', 'Ograve',
						  'Omega', 'Omicron', 'Oslash', 'Otilde', 'Ouml',
						  'Phi', 'Pi', 'Prime', 'Psi', 'Rho',
						  'Scaron', 'Sigma', 'THORN', 'Tau', 'Theta',
						  'Uacute', 'Ucirc', 'Ugrave', 'Upsilon', 'Uuml',
						  'Xi', 'Yacute', 'Yuml', 'Zeta', 'aacute',
						  'acirc', 'acute', 'aelig', 'agrave', 'alefsym',
						  'alpha', 'amp', 'and', 'ang', 'apos',
						  'aring', 'asymp', 'atilde', 'auml', 'bdquo',
						  'beta', 'brvbar', 'bull', 'cap', 'ccedil',
						  'cedil', 'cent', 'chi', 'circ', 'clubs',
						  'cong', 'copy', 'crarr', 'cup', 'curren',
						  'dArr', 'dagger', 'darr', 'deg', 'delta',
						  'diams', 'divide', 'eacute', 'ecirc', 'egrave',
						  'empty', 'emsp', 'ensp', 'epsilon', 'equiv',
						  'eta', 'eth', 'euml', 'euro', 'exist',
						  'fnof', 'forall', 'frac12', 'frac14', 'frac34',
						  'frasl', 'gamma', 'ge', 'gt', 'hArr',
						  'harr', 'hearts', 'hellip', 'iacute', 'icirc',
						  'iexcl', 'igrave', 'image', 'infin', 'int',
						  'iota', 'iquest', 'isin', 'iuml', 'kappa',
						  'lArr', 'lambda', 'lang', 'laquo', 'larr',
						  'lceil', 'ldquo', 'le', 'lfloor', 'lowast',
						  'loz', 'lrm', 'lsaquo', 'lsquo', 'lt',
						  'macr', 'mdash', 'micro', 'middot', 'minus',
						  'mu', 'nabla', 'nbsp', 'ndash', 'ne',
						  'ni', 'not', 'notin', 'nsub', 'ntilde',
						  'nu', 'oacute', 'ocirc', 'oelig', 'ograve',
						  'oline', 'omega', 'omicron', 'oplus', 'or',
						  'ordf', 'ordm', 'oslash', 'otilde', 'otimes',
						  'ouml', 'para', 'part', 'permil', 'perp',
						  'phi', 'pi', 'piv', 'plusmn', 'pound',
						  'prime', 'prod', 'prop', 'psi', 'quot',
						  'rArr', 'radic', 'rang', 'raquo', 'rarr',
						  'rceil', 'rdquo', 'real', 'reg', 'rfloor',
						  'rho', 'rlm', 'rsaquo', 'rsquo', 'sbquo',
						  'scaron', 'sdot', 'sect', 'shy', 'sigma',
						  'sigmaf', 'sim', 'spades', 'sub', 'sube',
						  'sum', 'sup', 'sup1', 'sup2', 'sup3',
						  'supe', 'szlig', 'tau', 'there4', 'theta',
						  'thetasym', 'thinsp', 'thorn', 'tilde', 'times',
						  'trade', 'uArr', 'uacute', 'uarr', 'ucirc',
						  'ugrave', 'uml', 'upsih', 'upsilon', 'uuml',
						  'weierp', 'xi', 'yacute', 'yen', 'yuml',
						  'zeta', 'zwj', 'zwnj');
	
		if (!$is_xhtml && ($index = array_search('apos', $entities)) !== false)
			array_splice($entities, $index, 1);
		
		$result = strtr($str, array("&" => "&amp;"));
		$result = preg_replace("/&amp;#(\\d+|x[\\da-f]+)(;?)/i", "&#\\1\\2", $result);
		$result = preg_replace("/&amp;(" . implode("|", $entities) . ")\\b/", "&\\1", $result);
		
		return $result;
	}
	
	static function trimLineBreak(string $s): string
	{
		return rtrim($s, "\r\n");
	}
	
	/** @return string[] */
	static function readLines(string $file, bool $skipEmptyLines = false): array
	{
		$rt = array();
		$fp = fopen($file, "r");
		
		while (!feof($fp))
		{
			$l = self::trimLineBreak(fgets($fp));
			
			if ($skipEmptyLines && self::isEmpty($l))
				continue;
			
			$rt[] = $l;
		}
		
		fclose($fp);
		
		return $rt;
	}
	
	/**
	 * @param string|(?string)[] $line
	 */
	static function convertLineToThreadEntry(string|array $line, ?ThreadEntry $entry = null): ?ThreadEntry
	{
		if (!is_array($line))
			$line = array_map(fn($_) => html_entity_decode($_, ENT_QUOTES), explode("<>", $line));
		
		if (count($line) < 12)
			return null;
		
		if (!$entry)
			$entry = new ThreadEntry();
		
		list
		(
			$id,
			$entry->title,
			$entry->name,
			$entry->mail,
			$entry->link,
			$eval,
			$points,
			$rate,
			$lastUpdate,
			$entry->host,
			$background,
			$foreground,
			$convertLineBreak,
			$tags
		) = $line + array_fill(0, 14, null);
		
		if ($id === null || $lastUpdate === null || $eval === null)
			return null;

		$entry->id = intval(strtr($id, array(".dat" => "")));
		$d = date_parse($lastUpdate);
		$entry->lastUpdate = mktime($d["hour"], $d["minute"], $d["second"], $d["month"], $d["day"], $d["year"]);
		$entry->tags = Util::splitTags($tags ?? "");
		$entry->dateTime = $entry->id;
		$entry->pageCount = 1;
		$evals = explode("/", $eval);
		
		if (Configuration::$instance->importCompositeEvalsAsCommentCount)
			$entry->commentCount = intval(array_pop($evals));
		else
			$entry->evaluationCount = intval(array_pop($evals));
		
		$entry->points = intval($points);
		$entry->rate = floatval($rate);
		unset
		(
			$id,
			$eval,
			$points,
			$rate,
			$lastUpdate,
			$background,
			$foreground,
			$convertLineBreak,
			$tags
		);
		
		return $entry;
	}
	
	/**
	 * @param string|string[] $dat
	 */
	private static function convertAndSaveToThreadInternal(PDO $db, PDO $idb, ?int $subject, string|array $dat, string $com, string $aft, bool $whenContainsWin31JOnly, bool $allowSaveCommentsOnly, bool &$save, ?ThreadEntry $entry): ?Thread
	{
		if (!is_array($dat) && !is_file($dat))
			return null;
		
		$containsWin31JOnly = false;
		$data = is_array($dat) ? $dat : array();
		
		if (!is_array($dat))
			foreach (self::readLines($dat) as $i)
			{
				$data[] = $j = mb_convert_encoding($i, "UTF-8", "Windows-31J");
				
				if ($whenContainsWin31JOnly && !$containsWin31JOnly && $j != mb_convert_encoding($i, "UTF-8", "SJIS"))
					$containsWin31JOnly = true;
			}
		
		$thread = new Thread();
		
		/** @var (?string)[] */
		$line = array_map(fn($_) => html_entity_decode($_, ENT_QUOTES), explode("<>", array_shift($data))) + array_fill(0, is_array($dat) ? 16 : 15, null);
		
		if (!is_array($dat))
			array_unshift($line, basename($dat));
		
		$thread->entry = $entry ?? ($entry = self::convertLineToThreadEntry($line, $thread->entry) ?? $thread->entry);
		
		$thread->updatePropertyLink();

		array_shift($line);
		$thread->subject = $subject;
		$thread->foreground = $line[10];
		$thread->convertLineBreak = is_null($line[11]) || $line[11] == "yes";
		$thread->hash = array_shift($data);
		
		if (preg_match('/^\#|^rgb/', $line[9] ?? ""))
			$thread->background = $line[9];
		else
			$thread->backgroundImage = $line[9];
		
		$thread->body = implode("\r\n", $data);
		unset($data);
		
		if (is_file($aft))
		{
			$afterData = array();
			
			foreach (self::readLines($aft) as $i)
			{
				$afterData[] = $j = mb_convert_encoding($i, "UTF-8", "Windows-31J");
			
				if ($whenContainsWin31JOnly && !$containsWin31JOnly && $j != mb_convert_encoding($i, "UTF-8", "SJIS"))
					$containsWin31JOnly = true;
			}
			
			$thread->afterword = implode("\r\n", $afterData);
			
			unset($afterData);
		}
		
		if ($thread->convertLineBreak)
		{
			$br = array("<br />\r\n" => "\r\n");
			$thread->body = strtr($thread->body, $br);
			$thread->afterword = isset($thread->afterword) ? strtr($thread->afterword, $br) : null;
		}
		
		$thread->entry->size = round(strlen(bin2hex(mb_convert_encoding($thread->body, "SJIS-Win", "UTF-8"))) / 2 / 1024, 2);
		
		if (is_file($com))
		{
			foreach (self::readLines($com) as $rawline)
			{
				$i = mb_convert_encoding($rawline, "UTF-8", "Windows-31J");
				$i = self::convertLinesToCommentsAndEvaluations($entry->id, array($i));
				
				if (!$i)
					continue;
				
				$i = $i[0];
				$i->save($db);
				
				if ($i instanceof Evaluation)
					$thread->evaluations[] = $thread->nonCommentEvaluations[] = $i;
				else
				{
					$thread->comments[] = $i;
					
					if ($i->evaluation)
						$thread->evaluations[] = $i->evaluation;
				}
			}
		}
		$save = !$whenContainsWin31JOnly || !$allowSaveCommentsOnly || $containsWin31JOnly;
		
		return $thread;
	}

	/**
	 * @return (Comment|Evaluation)[]
	 */
	static function convertLinesToCommentsAndEvaluations(int $entryID, array $lines): array
	{
		$rt = array();
		
		foreach ($lines as $i)
		{
			$i = array_map(fn($_) => html_entity_decode($_, ENT_QUOTES), explode("<>", trim($i)));
			
			if (count($i) < 7)
				continue;
			
			$comment = new Comment();
			list
			(
				$comment->body,
				$comment->name,
				$comment->mail,
				$dateTime,
				$point,
				$comment->hash,
				$comment->host
			) = $i;
			
			$point = intval($point);
			$d = date_parse($dateTime);
			$comment->id = $comment->dateTime = mktime($d["hour"], $d["minute"], $d["second"], $d["month"], $d["day"], $d["year"]);
			$comment->entryID = $entryID;
			
			if ($point || $comment->body == "#EMPTY#")
			{
				$eval = new Evaluation();
				$eval->id = $comment->id;
				$eval->entryID = $entryID;
				$eval->dateTime = $comment->dateTime;
				$eval->host = $comment->host;
				$eval->point = $point;
				
				if ($comment->body == "#EMPTY#")
					$rt[] = $eval;
				else
					$comment->evaluation = $eval;
			}
			
			if ($comment->body != "#EMPTY#")
			{
				$comment->body = strtr($comment->body, array("<br />" => "\r\n"));
				$rt[] = $comment;
			}
			
			unset
			(
				$i,
				$d,
				$comment,
				$eval,
				$point
			);
		}

		return $rt;
	}
	
	/**
	 * @param string|string[] $dat
	 */
	static function convertAndSaveToThread(PDO $db, PDO $idb, ?int $subject, string|array $dat, string $com, string $aft, bool $whenContainsWin31JOnly = false, bool $allowSaveCommentsOnly = false, ?ThreadEntry $entry = null): ?Thread
	{
		$save = false;
		$thread = self::convertAndSaveToThreadInternal($db, $idb, $subject, $dat, $com, $aft, $whenContainsWin31JOnly, $allowSaveCommentsOnly, $save, $entry);
		
		if ($thread && $save)
		{
			$thread->entry->updateCount($thread);
			$thread->save($db, false);
			SearchIndex::register($idb, $thread);
			
			return $thread;
		}

		return null;
	}

	static function isEmpty(?string $str): bool
	{
		return !isset($str[0]);
	}
	
	static function isLength(string $str, int $length): bool
	{
		return isset($str[$length - 1]) && !isset($str[$length]);
	}
	
	static function hasLength(string $str, int $length): bool
	{
		return isset($str[$length - 1]);
	}
	
	static function acquireWriteLock(string $name = "default", int $lifetime = 60): string
	{
		$path = rtrim(Constant::DATA_DIR, "/") . "/" . $name . ".lock";
		
		while (!@mkdir($path))
		{
			clearstatcache();
			$mtime = @filemtime($path . "/.");
			
			if ($mtime === false || time() - $mtime > $lifetime)
				break;
			
			usleep(50 * 1000);
		}
		
		return $path;
	}
	
	static function releaseLock(string $p): void
	{
		rmdir($p);
	}
}
?>
