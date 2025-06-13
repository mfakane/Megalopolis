<?php
namespace Megalopolis;

class Cookie
{
	const string COOKIE_NAME = "Cookie";
	const string NAME_KEY = "Name";
	const string MAIL_KEY = "Mail";
	const string LINK_KEY = "Link";
	const string PASSWORD_KEY = "Password";
	const string LAST_ID_KEY = "LastID";
	const string FONT_SIZE_KEY = "FontSize";
	const string MOBILE_VERTICAL_KEY = "MobileVertical";
	const string LIST_TYPE_KEY = "ListType";
	const string LIST_VISIBILITY_KEY = "ListVisibility";
	const string VIEW_HISTORY_KEY = "ViewHistory";
	const string EVALUATION_HISTORY_KEY = "EvaluationHistory";

	/** @var string[]|null */
	static ?array $data = null;
	
	private static function checkCookie(): void
	{
		if (self::$data !== null)
			return;
		
		self::$data = array();
		
		if (isset($_COOKIE[self::COOKIE_NAME]))
			foreach (explode("<", Util::escapeInput($_COOKIE[self::COOKIE_NAME])) as $i)
				if (mb_strstr($i, ">") !== false)
				{
					$s = explode(">", $i, 2);
					
					if (count($s) > 1 && $s[1] !== "")
						self::$data[urldecode($s[0])] = urldecode($s[1]);
				}
	}
	
	static function sendCookie(): void
	{
		if (self::$data !== null)
			setcookie
			(
				self::COOKIE_NAME,
				"<" . implode("<", array_map(fn($k, $v) => urlencode($k) . ">" . urlencode($v), array_keys(self::$data), array_values(self::$data))),
				time() + 60 * 60 * 24 * 30,
				dirname(Util::getPhpSelf())
			);
	}
	
	/**
	 * @template T of string|?string
	 * @param T $defaultValue
	 * @return (T is string ? string : ?string)
	 */
	static function getCookie(string $key, ?string $defaultValue = null): ?string
	{
		self::checkCookie();
		
		if (isset(self::$data[$key]))
			return self::$data[$key];
		else
			return $defaultValue;
	}
	
	static function setCookie(string $key, string $value): void
	{
		self::checkCookie();
		self::$data[$key] = $value;
	}
}
?>
