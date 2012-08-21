<?php
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
	
	/**
	 * @return string
	 */
	static function getBrowserType()
	{
		static $browserType;
		
		if ($browserType)
			return $browserType;
		
		$ua = $_SERVER["HTTP_USER_AGENT"];
		
		foreach (array
		(
			self::BROWSER_TYPE_IPHONE		=> self::BROWSER_TYPE_IPHONE,
			"iPod"							=> self::BROWSER_TYPE_IPHONE,
			self::BROWSER_TYPE_IPAD			=> self::BROWSER_TYPE_IPAD,
			self::BROWSER_TYPE_ANDROID		=> self::BROWSER_TYPE_ANDROID,
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
				return $browserType = $v;
		
		return $browserType = self::BROWSER_TYPE_UNKNOWN;
	}
	
	/**
	 * @return string
	 */
	static function getAbsoluteUrl($path = "")
	{
		$script = $_SERVER["SCRIPT_NAME"];
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
		
		return "http://" . $_SERVER["SERVER_NAME"] . ($_SERVER["SERVER_PORT"] == 80 ? null : ":" . $_SERVER["SERVER_PORT"]) . $script . $path;
	}
	
	static function getSuffix()
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
	
	/**
	 * @return string
	 */
	static function getPhpSelf()
	{
		static $phpSelf;
		
		return $phpSelf ? $phpSelf : $phpSelf = self::escapeInput($_SERVER["PHP_SELF"]);
	}
	
	/**
	 * @return string
	 */
	static function getPathInfo()
	{
		static $pathInfo;
		
		// TODO: HTTP_X_REWRITE_URL 後で調べる
		
		if ($pathInfo)
			return $pathInfo;
		else if (isset($_GET[self::PATH_INFO_QUERY_PARAM]))
			return $pathInfo = self::escapeInput($_GET[self::PATH_INFO_QUERY_PARAM]);
		else if ($rt = self::escapeInput(getenv("PATH_INFO")))
			return $pathInfo = $rt;
		else
			return $pathInfo = mb_substr(mb_strstr(self::escapeInput($_SERVER["PHP_SELF"]), self::INDEX_FILE_NAME), mb_strlen(self::INDEX_FILE_NAME));
	}
	
	/**
	 * @param string $tags
	 * @return array
	 */
	static function splitTags($tags)
	{
		return array_unique(preg_split("/ +/", mb_convert_kana(trim($tags), "s"), -1, PREG_SPLIT_NO_EMPTY));
	}
	
	/**
	 * @param PDOStatement $st
	 * @return PDOStatement
	 */
	static function ensureStatement(PDO $db, $st)
	{
		if ($st)
			return $st;
		else
			throw new ApplicationException(array_pop($db->errorInfo()));
	}
	
	/**
	 * @param bool $throw [optional]
	 * @return bool
	 */
	static function executeStatement(PDOStatement $st, array $params = null, $throw = true)
	{
		foreach(range(1, 5) as $i)
		{
			if (is_null($params))
				$rt = $st->execute();
			else
				$rt = $st->execute($params);
			
			if ($rt)
				break;
			
			$error = $st->errorInfo();
			
			if ($error != array("HY000", 5, "database is locked") &&
				$error != array("HY000", 21, "library routine called out of sequence"))
				break;
			
			usleep(5000);
		}
		
		if ($rt)
			return $rt;
		else if ($throw)
		{
			$error = $st->errorInfo();
			
			throw new ApplicationException("{$error[0]},{$error[1]}:{$error[2]}");
		}
		else
			return false;
	}
	
	/**
	 * @param string $name
	 */
	static function createTableIfNotExists(PDO $db, array $schema, $name)
	{
		$arr = array_map(create_function('$x, $y', 'return "{$x} {$y}";'), array_keys($schema), array_values($schema));

		Util::executeStatement(Util::ensureStatement($db, $db->prepare(strtr(sprintf
		("
			create table if not exists %s
			(
				%s,
				primary key(%s)
			)",
			$name,
			implode(", ", array_map(create_function('$_', 'return strtr($_, array(" primary key" => ""));'), $arr)),
			implode(", ", array_map(create_function('$_', 'return array_shift(explode(" ", $_));'), array_filter($arr, create_function('$_', 'return mb_strstr($_, "primary key");'))))
		), array(",
				primary key()" => "")))));
	}
	
	/**
	 * @param mixed $obj
	 * @param string $name
	 */
	static function saveToTable(PDO $db, $obj, array $schema, $name)
	{
		$st = Util::ensureStatement($db, $db->prepare(sprintf
		('
			insert or replace into %s
			(
				%s
			)
			values
			(
				:%s
			)',
			$name,
			implode(", ", array_keys($schema)),
			implode(", :", array_keys($schema))
		)));
		Util::bindValues($st, $obj, $schema);
		Util::executeStatement($st);
	}
	
	/**
	 * @param string $name
	 */
	static function hasTable(PDO $db, $name)
	{
		$st = self::ensureStatement($db, $db->prepare("select * from sqlite_master where type = 'table' and name = ?"));
		self::executeStatement($st, array($name));
		
		return count($st->fetchAll()) > 0;
	}
	
	/**
	 * @param mixed $obj
	 */
	static function bindValues(PDOStatement $st, $obj, array $schema)
	{
		foreach ($schema as $k => $v)
		{
			$type = explode(" ", $v, 2);
			$type = $type[0];
			
			if ($type == "integer")
				$type = PDO::PARAM_INT;
			else if ($type == "bit")
				$type = PDO::PARAM_BOOL;
			else
				$type = PDO::PARAM_STR;
			
			if (property_exists($obj, $k))
				$st->bindValue(":" . $k, $obj->$k, $type);
		}
	}
	
	/**
	 * @param string $raw
	 * @param int $version [optional]
	 * @param string $salt [optional]
	 * @param int $stretch [optional]
	 * @return string
	 */
	static function hash($raw, $version = 1, $salt = null, $stretch = null)
	{
		switch ($version)
		{
			case 1:
				if (!$salt)
					$salt = mb_substr(hash(self::HASH_ALGORITHM, mt_rand()), 4, 16);
				
				if (!$stretch)
					$stretch = mt_rand(10, 1000);
				
				if (strlen($salt) != 16)
					throw new ApplicationException("salt must be 16 chars");
				
				$rt = $raw;
				
				foreach (range(1, $stretch) as $i)
					$rt = "{$version}" . sprintf("%04d", $stretch) . hash(self::HASH_ALGORITHM, $rt) . "{$salt}";
				
				return $rt;
			default:
				throw new ApplicationException("Hash version {$version} not supported");
		}
	}

	/**
	 * @param string $hash
	 * @param string $raw
	 * @return bool|string
	 */
	static function hashEquals($hash, $raw)
	{
		try
		{
			if (empty($hash))
				return empty($raw);
			else if (strlen($hash) == 13 && crypt($raw, mb_substr($hash, 0, 2)) == $hash)
				return self::HASH_TYPE_ANTHOLOGYS;
			else if (strlen($hash) == 40 && sha1($raw) == $hash)
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
	
	/**
	 * @param string $input
	 * @return string
	 */
	static function escapeInput($input)
	{
		$input = mb_convert_encoding($input, "UTF-8");
		$input = self::escapeAmpersand($input);
		$input = self::decodeNumericEntity($input);
		$input = self::escapeControlChars($input);
		$input = mb_convert_encoding($input, "UTF-8");
		
		return $input;
	}
	
	/**
	 * @param string $pattern
	 * @param string $string
	 * @return bool
	 */
	static function wildcard($pattern, $string)
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
	
	private static function escapeControlChars($input)
	{
		return preg_replace('@[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]@', " ", $input);
	}
	
	private static function decodeNumericEntity($str, $encoding = null)
	{
		if (!$encoding)
			$encoding = mb_internal_encoding();
		
		$result = preg_replace("/&#x([\\dA-F]+);?/ie", "'&#'. intval('\\1', 16) . ';'", $str);
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
	
	private static function escapeAmpersand($str, $is_xhtml = true)
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
	
		if (!$is_xhtml)
		{
			$index = array_search('apos', $entities);
			array_splice($entities, $index, 1);
		}
		
		$result = strtr($str, array("&" => "&amp;"));
		$result = preg_replace("/&amp;#(\\d+|x[\\da-f]+)(;?)/i", "&#\\1\\2", $result);
		$result = preg_replace("/&amp;(" . implode("|", $entities) . ")\\b/", "&\\1", $result);
		
		return $result;
	}

	/**
	 * @param string $line
	 * @return ThreadEntry
	 */
	static function convertLineToThreadEntry($line, ThreadEntry $entry = null)
	{
		$line = array_map(create_function('$_', 'return html_entity_decode($_, ENT_QUOTES);'), explode("<>", $line));
		
		if (count($line) < 13)
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
		) = $line;
		
		$entry->id = intval(strtr($id, array(".dat" => "")));
		$d = date_parse($lastUpdate);
		$entry->lastUpdate = mktime($d["hour"], $d["minute"], $d["second"], $d["month"], $d["day"], $d["year"]);
		$entry->tags = Util::splitTags($tags);
		$entry->dateTime = $entry->id;
		$entry->pageCount = 1;
		$entry->evaluationCount = intval(array_pop(explode("/", $eval)));
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
	 * @param int $subject
	 * @param string $dat
	 * @param string $com
	 * @param string $aft
	 * @return Thread
	 */
	static function convertAndSaveToThread(PDO $db, PDO $idb, $subject, $dat, $com, $aft)
	{
		if (!is_file($dat))
			return null;
		
		$data = array_map(create_function('$_', 'return mb_convert_encoding($_, "UTF-8", "SJIS");'), file($dat, FILE_IGNORE_NEW_LINES));
		$thread = new Thread();
		
		self::convertLineToThreadEntry(basename($dat) . "<>" . $data[0], $thread->entry);
		$line = array_map(create_function('$_', 'return html_entity_decode($_, ENT_QUOTES);'), explode("<>", array_shift($data)));
		$thread->subject = $subject;
		$thread->foreground = $line[10];
		$thread->convertLineBreak = $line[11] == "yes";
		$thread->hash = array_shift($data);
		
		if (preg_match('/^\#|^rgb/', $line[9]))
			$thread->background = $line[9];
		else
			$thread->backgroundImage = $line[9];
		
		$thread->body = implode("\r\n", $data);
		unset($data);
		
		if (is_file($aft))
			$thread->afterword = mb_convert_encoding(implode("\r\n", file($aft, FILE_IGNORE_NEW_LINES)), "UTF-8", "SJIS");
		
		if ($thread->convertLineBreak)
		{
			$br = array("<br />\r\n" => "\r\n");
			$thread->body = strtr($thread->body, $br);
			$thread->afterword = strtr($thread->afterword, $br);
		}
		
		$thread->entry->size = round(strlen(bin2hex(mb_convert_encoding($thread->body, "SJIS", "UTF-8"))) / 2 / 1024, 2);
		
		if (is_file($com))
		{
			foreach (array_map(create_function('$_', 'return mb_convert_encoding($_, "UTF-8", "SJIS");'), file($com, FILE_IGNORE_NEW_LINES)) as $i)
			{
				$i = array_map(create_function('$_', 'return html_entity_decode($_, ENT_QUOTES);'), explode("<>", trim($i)));
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
				$comment->entryID = $thread->id;
				
				if ($point || $comment->body == "#EMPTY#")
				{
					$eval = new Evaluation();
					$eval->id = $comment->id;
					$eval->entryID = $comment->entryID;
					$eval->dateTime = $comment->dateTime;
					$eval->host = $comment->host;
					$eval->point = $point;
					$thread->evaluations[] = $eval;
					
					if ($comment->body == "#EMPTY#")
					{
						$eval->save($db);
						$thread->nonCommentEvaluations[] = $eval;
					}
					else
						$comment->evaluation = $eval;
				}
				
				if ($comment->body != "#EMPTY#")
				{
					$comment->body = strtr($comment->body, array("<br />" => "\r\n"));
					$comment->save($db);
					$thread->comments[] = $comment;
				}
				
				unset
				(
					$i,
					$comment,
					$d,
					$point,
					$rate
				);
			}
		}

		$thread->entry->updateCount($thread);
		$thread->save($db);
		SearchIndex::register($idb, $thread);
		
		return $thread;
	}

	/**
	 * @param string $str
	 * @return bool
	 */
	static function isEmpty($str)
	{
		return !isset($str[0]);
	}
}
?>