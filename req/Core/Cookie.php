<?php
class Cookie
{
	const COOKIE_NAME = "Cookie";
	const NAME_KEY = "Name";
	const MAIL_KEY = "Mail";
	const LINK_KEY = "Link";
	const PASSWORD_KEY = "Password";
	const LAST_ID_KEY = "LastID";
	const FONT_SIZE_KEY = "FontSize";
	const MOBILE_VERTICAL_KEY = "MobileVertical";
	const LIST_TYPE_KEY = "ListType";
	const LIST_VISIBILITY_KEY = "ListVisibility";
	const VIEW_HISTORY_KEY = "ViewHistory";
	const EVALUATION_HISTORY_KEY = "EvaluationHistory";
	
	static $data = null;
	
	private static function checkCookie()
	{
		if (self::$data)
			return;
		
		self::$data = array();
		
		if (isset($_COOKIE[self::COOKIE_NAME]))
			foreach (explode("<", Util::escapeInput($_COOKIE[self::COOKIE_NAME])) as $i)
				if (mb_strstr($i, ">"))
				{
					$s = explode(">", $i, 2);
					
					if (!Util::isEmpty($s[1]))
						self::$data[urldecode($s[0])] = urldecode($s[1]);
				}
	}
	
	static function sendCookie()
	{
		if (self::$data)
			setcookie
			(
				self::COOKIE_NAME,
				"<" . implode("<", array_map(create_function('$k, $v', 'return urlencode($k) . ">" . urlencode($v);'), array_keys(self::$data), array_values(self::$data))),
				time() + 60 * 60 * 24 * 30,
				dirname(Util::getPhpSelf())
			);
	}
	
	static function getCookie($key, $defaultValue = null)
	{
		self::checkCookie();
		
		if (isset(self::$data[$key]))
			return self::$data[$key];
		else
			return $defaultValue;
	}
	
	static function setCookie($key, $value)
	{
		self::checkCookie();
		self::$data[$key] = $value;
	}
}
?>