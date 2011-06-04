<?php
class Visualizer
{
	static $data;
	static $basePath;
	static $mode = null;

	const MODE_COOKIE_NAME = "VisualizerMode";
	
	static function isMobile()
	{
		return Util::getBrowserType() == Util::BROWSER_TYPE_IPHONE;
	}
	
	static function isSimple()
	{
		return Util::getBrowserType() == Util::BROWSER_TYPE_MOBILE;
	}
	
	static function doctype()
	{
		echo "<!DOCTYPE html>\r\n";
	}
	
	static function visualizerMode()
	{
		if (self::$mode)
			return self::$mode;
		else if (isset($_GET["visualizer"]))
		{
			self::$mode = Util::escapeInput($_GET["visualizer"]);
			
			if (self::$mode == "auto")
				self::$mode = self::autoVisualizerMode();
			
			setcookie(self::MODE_COOKIE_NAME, self::$mode, time() + 60 * 60 * 24 * 7, "/");
			
			return self::$mode;
		}
		else if (isset($_COOKIE[self::MODE_COOKIE_NAME]))
			return $_COOKIE[self::MODE_COOKIE_NAME];
		else
			return self::$mode = self::autoVisualizerMode();
	}
	
	private static function autoVisualizerMode()
	{
		if (self::isMobile())
			return "mobile";
		else if (self::isSimple())
			return "simple";
		else
			return "normal";
	}
	
	static function head()
	{
		$type = Util::getBrowserType();
		$isMobile = self::visualizerMode() == "mobile";
		$isSimple = self::visualizerMode() == "simple";
		
		?>
		<meta charset="UTF-8" />
		<meta name="application-name" content="<?php self::converted(Configuration::$instance->title) ?>" />
		<?php if ($isMobile): ?>
			<meta name="viewport" content="initial-scale=1.0, maximum-scale=1.0, user-scalable=no, width=device-width" />
			<meta name="format-detection" content="telephone=no" />
			<meta name="apple-mobile-web-app-capable" content="yes" />
			<link href="<?php self::converted(self::actionHref("style", "splash.png")) ?>" rel="apple-touch-startup-image" type="image/png" />
			<link href="<?php self::converted(self::actionHref("style", "iosIcon.png")) ?>" rel="apple-touch-icon" type="image/png" />
			<link href="http://code.jquery.com/mobile/1.0a4.1/jquery.mobile-1.0a4.1.min.css" rel="stylesheet" />
			<script src="http://code.jquery.com/jquery-1.6.1.min.js"></script>
			<script src="http://code.jquery.com/mobile/1.0a4.1/jquery.mobile-1.0a4.1.min.js"></script>
			<link href="<?php self::converted(self::actionHref("style", "mobile", "mobile.css")) ?>" rel="stylesheet" />
			<script src="<?php self::converted(self::actionHref("script", "base.js")) ?>"></script>
			<script src="<?php self::converted(self::actionHref("script", "mobile", "base.js")) ?>"></script>
		<?php elseif ($isSimple): ?>
			<link href="<?php self::converted(self::actionHref("style", "simple", "simple.css")) ?>" rel="stylesheet" />
		<?php else: ?>
			<script src="http://code.jquery.com/jquery-1.6.1.min.js"></script>
			<script src="<?php self::converted(self::actionHref("script", "jquery.easing.js")) ?>"></script>
			<script src="<?php self::converted(self::actionHref("script", "jquery.tmpl.js")) ?>"></script>
			<link href="<?php self::converted(self::actionHref("style", "style.css")) ?>" rel="stylesheet" />
			<script src="<?php self::converted(self::actionHref("script", "base.js")) ?>"></script>
		<?php endif ?>
		<link href="<?php self::converted(self::actionHref("style", "favicon.ico")) ?>" rel="shortcut icon" type="image/vnd.microsoft.icon" />
		<link href="<?php self::converted(self::actionHref("style", "favicon.ico")) ?>" rel="icon" type="image/vnd.microsoft.icon" />
		<?php
		
		if (!$isMobile && !$isSimple)
			if (in_array($type, array(Util::BROWSER_TYPE_MSIE6, Util::BROWSER_TYPE_MSIE7, Util::BROWSER_TYPE_MSIE_NEW, Util::BROWSER_TYPE_MSIE)))
			{
				?>
				<meta name="msapplication-starturl" content="<?php self::converted(self::absoluteHref()) ?>" />
				<?php if (!Configuration::$instance->adminOnly): ?>
					<meta name="msapplication-task" content="name=新規投稿;action-uri=<?php self::converted(self::absoluteHref("new")) ?>;icon-uri=<?php self::converted(self::absoluteHref("style", "addTaskIcon.png")) ?>" />
				<?php endif ?>
				<meta name="msapplication-task" content="name=おまかせ表示;action-uri=<?php self::converted(self::absoluteHref("random")) ?>;icon-uri=<?php self::converted(self::absoluteHref("style", "refreshTaskIcon.png")) ?>" />
				<meta name="msapplication-task" content="name=詳細検索;action-uri=<?php self::converted(self::absoluteHref("search")) ?>;icon-uri=<?php self::converted(self::absoluteHref("style", "searchTaskIcon.png")) ?>" />
				<!--[if lt IE 9]>
				<script src="<?php self::converted(self::actionHref("script", "html5.js")) ?>"></script>
				<![endif]-->
				<link href="<?php self::converted(self::actionHref("style", "trident.css")) ?>" rel="stylesheet" />
				<?php
			}
			else if (in_array($type, array(Util::BROWSER_TYPE_WEBKIT, Util::BROWSER_TYPE_IPHONE, Util::BROWSER_TYPE_IPAD)))
			{
				?>
				<link href="<?php self::converted(self::actionHref("style", "webkit.css")) ?>" rel="stylesheet" />
				<?php
			}
			else if (in_array($type, array(Util::BROWSER_TYPE_FIREFOX, Util::BROWSER_TYPE_FIREFOX2, Util::BROWSER_TYPE_GECKO)))
			{
				?>
				<link href="<?php self::converted(self::actionHref("style", "gecko.css")) ?>" rel="stylesheet" />
				<?php
			}
			else if ($type == Util::BROWSER_TYPE_PRESTO)
			{
				?>
				<link href="<?php self::converted(self::actionHref("style", "presto.css")) ?>" rel="stylesheet" />
				<?php
			}
		
		echo Configuration::$instance->head;
	}
	
	static function header($title, array $menu = array(), $subTitle = null)
	{
		array_unshift($menu, ", ホーム, homeIcon.png");
		?>
		<header>
			<a href="<?php self::converted(self::$basePath) ?>">
				<?php self::converted(Configuration::$instance->title) ?>
			</a>
			<form action="<?php self::converted(self::actionHref("search")) ?>" method="get">
				<div>
					<input type="search" name="query" placeholder="検索" />
					<input type="submit" value="検索" />
					<?php
					if (App::$actionName == "tag" && !is_array(Visualizer::$data))
						echo '<input type="hidden" name="tags" value="', Visualizer::$data, '" />';
					else if (App::$actionName == "author" && !is_array(Visualizer::$data))
						echo '<input type="hidden" name="name" value="', Visualizer::$data, '" />';
					?>
				</div>
			</form>
			<nav>
				<ul>
					<?php foreach ($menu as $i): ?>
						<?php
						if (!$i)
							continue;
						$values = explode(", ", $i);
						?>
						<li>
							<a href="<?php self::converted(self::$basePath . Util::getSuffix() . $values[0]) ?>">
								<?php if (isset($values[2])): ?><img src='<?php self::converted(self::$basePath . "style/" . $values[2]) ?>' width='16' height='16' /><?php endif ?><?php self::converted($values[1]) ?>
							</a>
						</li>
					<?php endforeach ?>
				</ul>
			</nav>
		</header>
		<h1>
			<?php self::converted($title) ?>
			<?php if (!Util::isEmpty($subTitle)): ?>
				<span><?php self::converted($subTitle) ?></span>
			<?php endif ?>
		</h1>
		<?php
	}
	
	static function footer()
	{
		?>
		<footer>
			<ul>
				<?php if (Configuration::$instance->showFooterVersion): ?>
					<li>
						<?php self::converted(App::NAME) ?>
						<?php self::converted(App::VERSION) ?>
					</li>
				<?php endif ?>
				<?php foreach (Configuration::$instance->footers as $i): ?>
					<li>
						<?php echo $i ?>
					</li>
				<?php endforeach ?>
				<li>
					<?php if (Auth::hasSession(true)): ?>
						<a href="<?php self::converted(self::actionHref("logout"))?>">
							ログアウト
						</a>
					<?php else: ?>
						<a href="<?php self::converted(self::actionHref("login"))?>">
							ログイン
						</a>
					<?php endif ?>
				</li>
				<li>
					Render time: __RENDER_TIME__
				</li>
				<!--<li>
					Process time: __PROCESS_TIME__
				</li>-->
			</ul>
			<a id="scrollToTop" href="#">
				ページの先頭へ	
			</a>
		</footer>
		<?php	
	}
	
	static function pager($current, $max, $range, $link, $reverse = false)
	{
		if ($max < 2)
			return;
		
		$loopback = in_array(Util::getBrowserType(), array
		(
			Util::BROWSER_TYPE_MSIE6,
			Util::BROWSER_TYPE_MSIE7,
			Util::BROWSER_TYPE_MSIE,
			Util::BROWSER_TYPE_MSIE_NEW
		)) ? "#" : "";
		$start = max(min($current - floor($range / 2), $max - $range + 1), 1);
		$end = min(max($current - ceil($range / 2), 0) + $range, $max);
		$isSimple = self::isSimple();
		
		?>
		<div class="pagerContainer">
			<ul class="pager">
				<?php if ($reverse): ?>
					<?php if ($current < $max || !$isSimple): ?>
						<?php if ($max > $range): ?>
							<li class="nav">
								<a href="<?php self::converted($current < $max ? $link . $max : $loopback) ?>">
									&lt;&lt; 最後
								</a>
							</li>
						<?php endif ?>
						<li class="nav">
							<a href="<?php self::converted($current < $max ? $link . ($current + 1) : $loopback) ?>">
								&lt; 次
							</a>
						</li>
					<?endif ?>
				<?php elseif ($current > 1 || !$isSimple): ?>
					<?php if ($max > $range): ?>
						<li class="nav">
							<a href="<?php self::converted($current > 1 ? $link . "1" : $loopback) ?>">
								&lt;&lt; 最初
							</a>
						</li>
					<?php endif ?>
					<li class="nav">
						<a href="<?php self::converted($current > 1 ? $link . ($current - 1) : $loopback) ?>">
							&lt; 前
						</a>
					</li>
				<?php endif ?>
				<?php foreach (range($reverse ? $end : max(min($current - floor($range / 2), $max - $range + 1), 1), $reverse ? $start : $end, $reverse ? -1 : 1) as $i): ?>
					<li>
						<a href="<?php self::converted($i == $current ? $loopback : $link . $i) ?>"<?=$i == $current ? ' class="active"' : null ?>>
							<?php self::converted($i) ?>
						</a>
					</li>
				<?php endforeach ?>
				<?php if ($reverse): ?>
					<?php if ($current > 1 || !$isSimple): ?>
						<li class="nav<?php if ($max <= $range) echo ' last' ?>">
							<a href="<?php self::converted($current > 1 ? $link . ($current - 1) : $loopback) ?>">
								前 &gt;
							</a>
						</li>
						<?php if ($max > $range): ?>
							<li class="nav last">
								<a href="<?php self::converted($current > 1 ? $link . "1" : $loopback) ?>">
									&gt;&gt; 最初
								</a>
							</li>
						<?php endif ?>
					<?php endif ?>
				<?php elseif ($current < $max || !$isSimple): ?>
					<li class="nav<?php if ($max <= $range) echo ' last' ?>">
						<a href="<?php self::converted($current < $max ? $link . ($current + 1) : $loopback) ?>">
							次 &gt;
						</a>
					</li>
					<?php if ($max > $range): ?>
						<li class="nav last">
							<a href="<?php self::converted($current < $max ? $link . $max : $loopback) ?>">
								最後 &gt;&gt;
							</a>
						</li>
					<?php endif ?>
				<?php endif ?>
			</ul>
		</div>
		<?php
	}
	
	private static function href($arr)
	{
		static $encodeTable = array();
		
		$href = "";
		
		foreach ($arr as $i)
			if (is_array($i))
			{
				$href .= "?";
				
				foreach ($i as $k => $v)
					$href .= (isset($encodeTable[$k]) ? $encodeTable[$k] : $encodeTable[$k] = urlencode($k)) . "=" . (isset($encodeTable[$v]) ? $encodeTable[$v] : $encodeTable[$v] = urlencode($v)) . "&";
				
				$href = rtrim($href, "&");
			}
			else
				$href .= "/" . (isset($encodeTable[$i]) ? $encodeTable[$i] : $encodeTable[$i] = urlencode($i));
		
		return trim($href, "/");
	}
	
	static function actionHref()
	{
		$href = self::href(func_get_args());
		
		return self::$basePath . (is_file($href) ? "" : Util::getSuffix()) . $href;
	}
	
	static function absoluteHref()
	{
		return Util::getAbsoluteUrl(self::href(func_get_args()));
	}
	
	static function statusCode($code)
	{
		switch ($code)
		{
			case 201:
				$code .= " Created";
				
				break;
			case 304:
				$code .= " Not Modified";
				
				break;
			case 403:
				$code .= " Forbidden";
				
				break;
			case 404:
				$code .= " Not Found";
				
				break;
			case 500:
				$code .= " Internal Server Error";
				
				break;
		}
		
		header("HTTP/1.1 {$code}");
		header("Status: {$code}");
	}
	
	/**
	 * @param string $s
	 */
	static function converted($s)
	{
		if (!is_null($s))
			echo self::escapeOutput($s);
	}
	
	/**
	 * @param string $s
	 */
	static function convertedName($s)
	{
		self::converted(empty($s) ? Configuration::$instance->defaultName : $s);
	}
	
	/**
	 * @param string $s
	 */
	static function convertedSummary($s)
	{
		echo self::escapeSummary($s);
	}
	
	/**
	 * @param string $s
	 */
	static function escapeSummary($s)
	{
		return preg_replace("/^(https?|ftp)(:\/\/[-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)$/", '<a href="$0">$0</a>', strtr(self::escapeOutput($s), array
		(
			"\r\n" => "<br />",
			"\r" => "<br />",
			"\n" => "<br />"
		)));
	}
	
	static function convertedBody(Thread $thread, $page = null, $offset = null, $length = null)
	{
		echo self::escapeBody($thread, $page, $offset, $length);
	}
	
	static function escapeBody(Thread $thread, $page = null, $offset = null, $length = null)
	{
		$content = $page ? $thread->page($page) : $thread->body;
		$s = self::ensureHtml(!is_null($offset) && $length ? mb_substr($content, $offset, $length) : $content);
		
		if ($thread->convertLineBreak)
			return self::convertLineBreak($s);
		else
			return $s;
	}
	
	static function convertedAfterword(Thread $thread)
	{
		echo self::escapeAfterword($thread);
	}
	
	static function escapeAfterword(Thread $thread)
	{
		$s = self::ensureHtml($thread->afterword);
		
		if ($thread->convertLineBreak)
			return self::convertLineBreak($s);
		else
			return $s;
	}
	
	static function convertLineBreak($s)
	{
		return strtr($s, array
		(
			"\r\n" => "<br />",
			"\n" => "<br />",
			"\r" => "<br />"
		));
	}
	
	/**
	 * @param string $s
	 * @return string
	 */
	static function escapeOutput($s)
	{
		if (!is_null($s))
			return htmlspecialchars($s, ENT_QUOTES);
	}
	
	private static function ensureHtml($str)
	{
		$rt = str_get_html($str);
		self::ensureHtmlTagEnd($rt);
		
		foreach (Configuration::$instance->disallowedTags as $i)
			foreach ($rt->find($i) as $j)
				$j->outertext = " :REPLACED: ";
		
		$str = $rt->save();
		$rt->clear();
		unset($rt);

		return $str;
	}
	
	private static function ensureHtmlTagEnd($rt)
	{
		foreach ($rt->find("*") as $i)
		{
			if (Util::isEmpty($i->outertext))
				continue;
			
			foreach ($i->attr as $k => $v)
			{
				foreach (Configuration::$instance->disallowedAttributes as $j)
					if (strpos($j, "regex:") === 0 &&
						preg_match('/^' . substr($j, 6) . '$/i', $k) ||
						$k == $j)
						$i->$k = null;
				
				if ($k == "style")
				{
					error_reporting(E_ALL ^ E_NOTICE);
					$css = new cssparser();
					$css->ParseStr("c {" . preg_replace_callback('/\\\([0-9A-Fa-f]{1,6})/i', create_function('$_', '$a = intval($_[1], 16); return $a >= 32 && $a <= 126 ? chr($a) : $_[0];'), $v) . "}");
					
					foreach ($css->css as $selector => $properties)
					{
						$keys = array_filter(array_keys($properties), create_function('$_', 'return preg_match("/b.+havio.+$/i", $_);'));
					
						foreach ($keys as $j)
							unset($properties[$j]);
						
						$i->$k = "";
					
						foreach ($properties as $k2 => $v2)
						{
							$v2 = preg_replace('/\b(.+[xｘＸ][pｐＰ][rｒＲ].+[sｓＳ][sｓＳ][iｉＩ][oｏＯ].+|data:|javascript:)\b/i', "", $v2);
							$i->$k .= "{$k2}: {$v2};";
						}
					}
					
					error_reporting(E_ALL);
				}
			}
			
			self::ensureHtmlTagEnd($i);
			
			if ($s = mb_strpos($i->plaintext, "<") !== false &&
				mb_strpos($i->plaintext, ">") !== false)
				$i->innertext = strip_tags($i->plaintext);	// tag in a plain text? huh?
			else if (!preg_match('@/>$@', $i->outertext) &&
				!preg_match('@</\s*' . $i->tag . '\s*>$@i', $i->outertext))
				$i->outertext .= "</{$i->tag}>";
		}
	}
	
	/**
	 * @param int $time
	 * @return string
	 */
	static function formatDateTime($time)
	{
		return date("Y/m/d H:i:s", $time);
	}
	
	/**
	 * @param int $time
	 * @return string
	 */
	static function formatShortDateTime($time)
	{
		$now = time();
		$minute = 60;
		$hour = $minute * 60;
		$day = $hour * 24;
		$diff = $now - $time;
		
		if ($diff < -$day)
			return ceil(-$day) . " 日先 " . date("H:i", $time);	
		else if ($diff < -$hour)
			return ceil(-$hour) . " 時間先";	
		else if ($diff < -$minute)
			return ceil(-$diff / $minute) . " 分先";	
		else if ($diff < 0)
			return ceil(-$diff) . " 秒先";
		else if ($diff < $minute)
			return floor($diff) . " 秒前";	
		else if ($diff < $hour)
			return floor($diff / $minute) . " 分前";
		else if ($diff < $day)
			return floor($diff / $hour) . " 時間前";
		else if ($diff < $day * 3)
			return floor($diff / $day) . " 日前 " . date("H:i", $time);
		else
			return date("m/d H:i", $time);
	}

	/**
	 * @param string $path
	 * @param int $status
	 * @param string $contentType
	 * @return mixed
	 */ 
	static function visualize($path = null, $status = null, $contentType = null)
	{
		Auth::commitSession();
		
		if ($path == null)
			if (is_dir(APP_DIR . VISUALIZER_DIR . App::$handlerName))
				$path = App::$handlerName . "/" . ucfirst(App::$actionName);
			else
				$path = App::$handlerName;
		
		$basePath = APP_DIR . VISUALIZER_DIR;
		$mode = self::visualizerMode();
		
		if ($mode == "mobile" &&
			is_file("{$basePath}mobile/{$path}.php"))
			$basePath .= "mobile/";
		else if ($mode == "simple" &&
			is_file("{$basePath}simple/{$path}.php"))
			$basePath .= "simple/";
			
		$path = "{$basePath}{$path}.php";
		
		if (!is_file($path))
			throw new ApplicationException("Visualizer {$path} not found");
		
		if ($status)
			self::statusCode($status);
		
		if ($contentType)
			header("Content-Type: {$contentType}");
		else
			header("Content-Type: text/html; charset=UTF-8");
		
		$table = array
		(
			'/<\?\+\s*(.*?)\s*\?>/s' => '<? Visualizer::converted($1) ?>',
			'/<\?=\s*(.*?)\s*\?>/s' => '<?echo $1 ?>',
			'/<\?\s*(.*?)\s*\?>/s' => '<?php $1 ?>',
			'/<\?php php/' => '<?php'
		);
		$content = preg_replace(array_keys($table), array_values($table), file_get_contents($path));
		
		$start = microtime(true);
		ob_start();
		eval("?" . ">" . $content);
		$output = ob_get_contents();
		ob_end_clean();
		$output = preg_replace('/\s+</S', '<', preg_replace('/>\s+/S', '>', $output));
		
		echo strtr($output, array
		(
			"<!DOCTYPE html>" => "<!DOCTYPE html>\r\n",
			"__RENDER_TIME__" => round((microtime(true) - $start) * 1000, 2) . "ms",
			"__PROCESS_TIME__" => round(($start - App::$startTime) * 1000, 2) . "ms"
		));

		return true;
	}
	
	/**
	 * @param mixed $obj
	 */
	static function json($obj)
	{
		header("Content-Type: application/json");
		
		echo json_encode($obj);
		
		return true;
	}
	
	/**
	 * @param string $path
	 * @param int $status
	 * @return mixed
	 */ 
	static function redirect($path = "", $status = null)
	{
		Auth::commitSession();
		
		if ($status)
			self::statusCode($status);
		
		header("Location: " . Util::getAbsoluteUrl($path));
		
		return true;
	}
	
	/**
	 * @param string $content
	 * @param string $encoding
	 * @return mixed
	 */ 
	static function text($content, $encoding = "UTF-8")
	{
		Auth::commitSession();
		
		mb_http_output($encoding);
		header("Content-Type: text/plain; charset={$encoding}");
		echo mb_convert_encoding($content, $encoding, "UTF-8");
		
		return true;
	}
}

Visualizer::$basePath = str_repeat("../", mb_substr_count(Util::getPathInfo(), "/"));

if (Util::isEmpty(Visualizer::$basePath))
	Visualizer::$basePath = "./";
?>