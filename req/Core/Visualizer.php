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
		return in_array(Util::getBrowserType(), array
		(
			Util::BROWSER_TYPE_ANDROIDMOBILE,
			Util::BROWSER_TYPE_PSP,
			Util::BROWSER_TYPE_3DS,
			Util::BROWSER_TYPE_MOBILE,
		));
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
		<?php if ($isMobile): ?>
			<meta name="viewport" content="initial-scale=1.0, maximum-scale=1.0, user-scalable=no, width=device-width" />
			<meta name="format-detection" content="telephone=no" />
			<meta name="apple-mobile-web-app-capable" content="yes" />
			<link href="<?php self::converted(self::actionHref("style", "splash.png")) ?>" rel="apple-touch-startup-image" type="image/png" />
			<link href="<?php self::converted(self::actionHref("style", "iosIcon.png")) ?>" rel="apple-touch-icon" type="image/png" />
			<link href="http://code.jquery.com/mobile/1.2.0-beta.1/jquery.mobile-1.2.0-beta.1.min.css" rel="stylesheet" />
			<link href="<?php self::converted(self::actionHref("style", "mobile", "mobile.css")) ?>" rel="stylesheet" />
			<script src="http://code.jquery.com/jquery-1.8.1.min.js"></script>
			<script src="<?php self::converted(self::actionHref("script", "base.js")) ?>"></script>
			<script src="<?php self::converted(self::actionHref("script", "mobile", "base.js")) ?>"></script>
			<script src="http://code.jquery.com/mobile/1.2.0-beta.1/jquery.mobile-1.2.0-beta.1.min.js"></script>
		<?php elseif ($isSimple): ?>
			<meta name="viewport" content="initial-scale=1.0, maximum-scale=1.0, user-scalable=no, width=device-width" />
			<link href="<?php self::converted(self::actionHref("style", "simple", "simple.css")) ?>" rel="stylesheet" />
		<?php else: ?>
			<meta name="application-name" content="<?php self::converted(Configuration::$instance->title) ?>" />
			<script src="http://code.jquery.com/jquery-1.8.1.min.js"></script>
			<link href="<?php self::converted(self::actionHref("style", "style.css")) ?>" rel="stylesheet" />
			<?php if (Configuration::$instance->skin): ?>
				<link href="<?php self::converted(self::actionHref("style", Configuration::$instance->skin, "style.css")) ?>" rel="stylesheet" />
			<?php endif ?>
			<script src="<?php self::converted(self::actionHref("script", "base.js")) ?>"></script>
		<?php endif ?>
		<link href="<?php self::converted(self::absoluteHref()) ?>" rel="home" />
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
				<?php if (Configuration::$instance->useSearch): ?>
					<meta name="msapplication-task" content="name=詳細検索;action-uri=<?php self::converted(self::absoluteHref("search")) ?>;icon-uri=<?php self::converted(self::absoluteHref("style", "searchTaskIcon.png")) ?>" />
				<?php endif ?>
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
	
	/**
	 * @param string $title [optional]
	 * @param array $menu [optional]
	 * @param string $subTitle [optional]
	 */
	static function header($title = null, array $menu = array(), $subTitle = null)
	{
		$menu = array_reverse($menu, true);
		$menu[""] = array("ホーム", "homeIcon.png");
		$menu = array_reverse($menu, true);
		?>
		<header>
			<a href="<?php self::converted(self::$basePath) ?>">
				<?php self::converted(Configuration::$instance->title) ?>
			</a>
			<?php if ((Configuration::$instance->showTitle[Configuration::ON_SUBJECT] && Configuration::$instance->useSearch) || Configuration::$instance->customSearch || Auth::hasSession(true)): ?>
				<form action="<?php self::converted(Configuration::$instance->customSearch ? Configuration::$instance->customSearch[0] : self::actionHref("search")) ?>" method="get">
					<div>
						<input type="search" name="<?php self::converted(Configuration::$instance->customSearch ? Configuration::$instance->customSearch[1] : "query") ?>" placeholder="検索" />
						<input type="submit" value="検索" />
						<?php
						if (isset(Configuration::$instance->customSearch[2]))
							foreach (Configuration::$instance->customSearch[2] as $k => $v)
								echo '<input type="hidden" name="', self::converted($k), '" value="', self::converted($v), '" />';
						?>
						<?php
						if (App::$actionName == "tag" && !is_array(Visualizer::$data))
							echo '<input type="hidden" name="tags" value="', Visualizer::$data, '" />';
						else if (App::$actionName == "author" && !is_array(Visualizer::$data))
							echo '<input type="hidden" name="name" value="', Visualizer::$data, '" />';
						?>
					</div>
				</form>
			<?php endif ?>
			<nav>
				<ul>
					<?php foreach ($menu as $k => $v): ?>
						<?php
						if (!is_array($v))
							continue;
						
						$name = $v[0];
						$icon = isset($v[1]) && !Util::isEmpty($v[1]) ? $v[1] : null;
						?>
						<li>
							<a href="<?php self::converted(self::$basePath . Util::getSuffix() . $k) ?>">
								<?php if (!is_null($icon)): ?><img src='<?php self::converted(self::$basePath . "style/" . $icon) ?>' width='16' height='16' alt='' /><?php endif ?><?php self::converted($name) ?>
							</a>
						</li>
					<?php endforeach ?>
				</ul>
			</nav>
		</header>
		<?php if (!Util::isEmpty($title)): ?>
			<h1>
				<?php self::converted($title) ?>
				<?php if (!Util::isEmpty($subTitle)): ?>
					<span><?php self::converted($subTitle) ?></span>
				<?php endif ?>
			</h1>
		<?php
		endif;
	}
	
	static function footer($backgroundColor = null)
	{
		$pathInfo = trim(Util::getPathInfo(), "/");
		$redir = Util::isEmpty($pathInfo) ? null : array("redir" => $pathInfo);
		
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
				<?php if (Auth::hasSession(true)): ?>
					<li>
						<a href="<?php self::converted(self::actionHref("util")) ?>">管理用ツール</a>
					</li>
				<?php endif ?>
				<li>
					<?php if (Auth::hasSession(true)): ?>
						<a href="<?php self::converted(self::actionHref("logout", $redir)) ?>">ログアウト</a>
					<?php else: ?>
						<a href="<?php self::converted(self::actionHref("login", $redir)) ?>">ログイン</a>
					<?php endif ?>
				</li>
				<li>
					Render time: __RENDER_TIME__
				</li>
				<!--<li>
					Process time: __PROCESS_TIME__
				</li>-->
			</ul>
			<a id="scrollToTop" href="#"<?php if (!Util::isEmpty($backgroundColor)): ?> style="background-color: <?php echo $backgroundColor ?>;"<?php endif ?>>
				ページの先頭へ	
			</a>
		</footer>
		<?php	
	}
	
	static function pager($current, $max, $range, $link, $reverse = false, $buttons = true, $container = true)
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
		
		list($prefix, $suffix) = (is_array($link) ? $link : array($link)) + array("", "");
		
		?>
		<?php if ($container): ?>
			<div class="pagerContainer">
		<?php endif ?>
			<ul class="pager">
				<?php if ($buttons): ?>
					<?php if ($reverse): ?>
						<?php if ($current < $max || !$isSimple): ?>
							<?php if ($max > $range): ?>
								<li class="nav">
									<a href="<?php self::converted($current < $max ? $prefix . $max . $suffix : $loopback) ?>">
										&lt;&lt; 最後
									</a>
								</li>
							<?php endif ?>
							<li class="nav">
								<a href="<?php self::converted($current < $max ? $prefix . ($current + 1) . $suffix : $loopback) ?>">
									&lt; 次
								</a>
							</li>
						<?php endif ?>
					<?php elseif ($current > 1 || !$isSimple): ?>
						<?php if ($max > $range): ?>
							<li class="nav">
								<a href="<?php self::converted($current > 1 ? $prefix . "1" . $suffix : $loopback) ?>">
									&lt;&lt; 最初
								</a>
							</li>
						<?php endif ?>
						<li class="nav">
							<a href="<?php self::converted($current > 1 ? $prefix . ($current - 1) . $suffix : $loopback) ?>">
								&lt; 前
							</a>
						</li>
					<?php endif ?>
				<?php endif ?>
				<?php foreach (range($reverse ? $end : max(min($current - floor($range / 2), $max - $range + 1), 1), $reverse ? $start : $end, $reverse ? -1 : 1) as $i): ?>
					<li>
						<a href="<?php self::converted($i == $current ? $loopback : $prefix . $i . $suffix) ?>"<?php echo $i == $current ? ' class="active"' : null ?>>
							<?php self::converted($i) ?>
						</a>
					</li>
				<?php endforeach ?>
				<?php if ($buttons): ?>
					<?php if ($reverse): ?>
						<?php if ($current > 1 || !$isSimple): ?>
							<li class="nav<?php if ($max <= $range) echo ' last' ?>">
								<a href="<?php self::converted($current > 1 ? $prefix . ($current - 1) . $suffix : $loopback) ?>">
									前 &gt;
								</a>
							</li>
							<?php if ($max > $range): ?>
								<li class="nav last">
									<a href="<?php self::converted($current > 1 ? $prefix . "1" . $suffix : $loopback) ?>">
										&gt;&gt; 最初
									</a>
								</li>
							<?php endif ?>
						<?php endif ?>
					<?php elseif ($current < $max || !$isSimple): ?>
						<li class="nav<?php if ($max <= $range) echo ' last' ?>">
							<a href="<?php self::converted($current < $max ? $prefix . ($current + 1) . $suffix : $loopback) ?>">
								次 &gt;
							</a>
						</li>
						<?php if ($max > $range): ?>
							<li class="nav last">
								<a href="<?php self::converted($current < $max ? $prefix . $max . $suffix : $loopback) ?>">
									最後 &gt;&gt;
								</a>
							</li>
						<?php endif ?>
					<?php endif ?>
				<?php endif ?>
			</ul>
		<?php if ($container): ?>
			</div>
		<?php endif ?>
		<?php
	}

	static function submitPager($current, $max, $range, $pageParam)
	{
		if ($max < 2)
			return;
		
		$start = max(min($current - floor($range / 2), $max - $range + 1), 1);
		$end = min(max($current - ceil($range / 2), 0) + $range, $max);
		$isSimple = self::isSimple();
		
		?>
		<ul class="pager">
			<?php if ($current > 1 || !$isSimple): ?>
				<?php if ($max > $range): ?>
					<li class="nav">
						<input type="submit" value="&lt;&lt; 最初" />
						<button name="<?php echo $pageParam ?>" value="1"<?php if ($current == 1) echo ' class="loopback"'; ?>>
							&lt;&lt; 最初
						</button>
					</li>
				<?php endif ?>
				<li class="nav">
					<button name="<?php echo $pageParam ?>" value="<?php echo max($current - 1, 1) ?>"<?php if ($current == 1) echo ' class="loopback"'; ?>>
						&lt; 前
					</button>
				</li>
			<?php endif ?>
			<?php foreach (range(max(min($current - floor($range / 2), $max - $range + 1), 1), $end, 1) as $i): ?>
				<li>
					<button name="<?php echo $pageParam ?>" value="<?php echo $i ?>"<?php if ($i == $current) echo ' class="active loopback"'; ?>>
						<?php self::converted($i) ?>
					</button>
				</li>
			<?php endforeach ?>
			<?php if ($current < $max || !$isSimple): ?>
				<li class="nav<?php if ($max <= $range) echo ' last' ?>">
					<button name="<?php echo $pageParam ?>" value="<?php echo min($current + 1, $max)?>"<?php if ($current == $max) echo ' class="loopback"'; ?>>
						次 &gt;
					</button>
				</li>
				<?php if ($max > $range): ?>
					<li class="nav last">
						<button name="<?php echo $pageParam ?>" value="<?php echo $max ?>"<?php if ($current == $max) echo ' class="loopback"'; ?>>
							最後 &gt;&gt;
						</button>
					</li>
				<?php endif ?>
			<?php endif ?>
		</ul>
		<?php
	}
	
	/**
	 * @param string $url [optional]
	 * @param string $text [optional]
	 * @param string $hashtags [optional]
	 * @param array $keywords [optional]
	 */
	static function tweetButton($url = "", $text = null, $hashtags = "", array $keywords = array())
	{
		$params = array_filter(array
		(
			"text" => strtr($text, $keywords),
			"url" => strtr($url, $keywords),
			"hashtags" => strtr($hashtags, $keywords),
		));
		
		?>
		<a href="https://twitter.com/share?<?php self::converted(implode("&", array_map(create_function('$k, $v', 'return rawurlencode($k) . "=" . rawurlencode($v);'), array_keys($params), array_values($params)))) ?>" class="twitter-share-button" data-lang="ja" target="_blank">Tweet</a>
		<script src="http://platform.twitter.com/widgets.js"></script>
		<?php
	}
	
	private static function href($arr)
	{
		static $encodeTable = array();
		
		$href = "";
		
		foreach ($arr as $i)
			if (is_null($i))
				continue;
			else if (is_array($i))
			{
				$href .= strpos(Util::getSuffix(), "?") !== false ? "&" : "?";
				
				foreach ($i as $k => $v)
					$href .= (isset($encodeTable[$k]) ? $encodeTable[$k] : $encodeTable[$k] = str_ireplace("%2F", "%252F", urlencode($k))) . "=" . (isset($encodeTable[$v]) ? $encodeTable[$v] : $encodeTable[$v] = urlencode($v)) . "&";
				
				$href = rtrim($href, "&");
			}
			else
				$href .= "/" . (isset($encodeTable[$i]) ? $encodeTable[$i] : $encodeTable[$i] = str_ireplace("%2F", "%252F", urlencode($i)));
		
		return trim($href, "/");
	}
	
	static function actionHref()
	{
		$args = func_get_args();
		
		return self::actionHrefArray($args);
	}
	
	static function actionHrefArray($args = null)
	{
		if (is_null($args))
			return self::$basePath;
		else
		{
			$href = self::currentHrefArray($args);
			
			return self::$basePath . (is_file($href) ? "" : Util::getSuffix()) . $href;
		}
	}
	
	static function currentHref()
	{
		$args = func_get_args();
		
		return self::currentHrefArray($args);
	}
	
	static function currentHrefArray($args)
	{
		return rtrim(self::href($args), "?");
	}
	
	static function absoluteHref()
	{
		return self::absoluteHrefArray(func_get_args());
	}
	
	static function absoluteHrefArray($args)
	{
		return Util::getAbsoluteUrl(self::href($args));
	}
	
	static function noCache()
	{
		header("Expires: Thu, 19 Nov 1981 08:52:00 GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
		header("Pragma: no-cache");
	}
	
	static function notModified()
	{
		self::statusCode(304);
		exit;
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
			case 401:
				$code .= " Unauthorized";
				
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
	static function linkedName($s, $additional = "")
	{
		if (empty($s))
			self::converted(Configuration::$instance->defaultName);
		else
		{
			$endsWithDigit = ctype_digit($s) || ($last = strrchr($s, "/")) !== false && ctype_digit(substr($last, 1));
			$endsWithExtension = !$endsWithDigit && strpos($s, ".") !== false;
			?>
			<a href="<?php echo self::actionHref("author", $s . ($endsWithExtension ? ".html" : null), $endsWithDigit ? "1" : null) ?>">
				<?php self::converted($s) ?>
				<?php echo $additional ?>
			</a>
			<?php
		}
	}
	
	/**
	 * @param string $s
	 */
	static function linkedTag($s, $additional = "")
	{
		$endsWithDigit = ctype_digit($s) || ($last = strrchr($s, "/")) !== false && ctype_digit(substr($last, 1));
		$endsWithExtension = !$endsWithDigit && strpos($s, ".") !== false;
		?>
		<a href="<?php echo self::actionHref("tag", $s . ($endsWithExtension ? ".html" : null), $endsWithDigit ? "1" : null) ?>">
			<?php self::converted($s) ?>
			<?php echo $additional ?>
		</a>
		<?php
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
	
	static function convertedBody(Thread $thread, $page = null, $offset = null, $length = null, $stripExcept = null)
	{
		echo self::escapeBody($thread, $page, $offset, $length, $stripExcept);
	}
	
	static function escapeBody(Thread $thread, $page = null, $offset = null, $length = null, $stripExcept = null)
	{
		$content = $page ? $thread->page($page) : $thread->body;
		$s = self::ensureHtml(!is_null($offset) && $length ? mb_substr($content, $offset, $length) : $content, $stripExcept);
		
		if ($thread->convertLineBreak)
			return self::convertLineBreak($s);
		else
			return $s;
	}
	
	static function convertedAfterword(Thread $thread, $stripExcept = null)
	{
		echo self::escapeAfterword($thread, $stripExcept);
	}
	
	static function escapeAfterword(Thread $thread, $stripExcept = null)
	{
		$s = self::ensureHtml($thread->afterword, $stripExcept);
		
		if ($thread->convertLineBreak)
			return self::convertLineBreak($s);
		else
			return $s;
	}
	
	static function convertLineBreak($s)
	{
		return strtr($s, array
		(
			"\r\n" => "<br />\r\n",
			"\n" => "<br />\r\n",
			"\r" => "<br />\r\n"
		));
	}
	
	/**
	 * @param string $s
	 * @return string
	 */
	static function escapeOutput($s)
	{
		if (!is_null($s))
			return htmlspecialchars($s, ENT_QUOTES, "UTF-8");
	}
	
	private static function ensureHtml($str, $stripExcept = null)
	{
		$rt = str_get_html($str);
		$disallowed = Configuration::$instance->disallowedTags;
		$allowed = array_flip(Configuration::$instance->allowedTags);
		$disallowedMap = array_flip(array_map(create_function('$x, $y', 'return is_int($x) ? $y : $x;'), array_keys($disallowed), array_values($disallowed)));
		self::replaceTags($rt, $disallowed, $disallowedMap, $allowed);
		self::ensureHtmlTagEnd($rt);
		
		$str = $rt->save();
		$rt->clear();
		unset($rt);
		
		if (!is_array($stripExcept))
			$stripExcept = Configuration::$instance->allowedTags;
			
		if ($stripExcept)
		{
			$str = preg_replace('@<([^/\sa-zA-Z])@i', '&lt;$1', $str);
			$str = strip_tags($str, "<" . implode("><", $stripExcept) . ">");
		}
		
		return $str;
	}
	
	private static function replaceTags($rt, array $disallowed, array $disallowedMap, array $allowed)
	{
		foreach ($rt->find("*") as $i)
		{
			if (isset($disallowedMap[$i->tag]))
				if (isset($disallowed[$i->tag]))
					$i->tag = $disallowed[$i->tag];
				else
				{
					$i->outertext = " :REPLACED: ";
					
					continue;
				}
			
			if (!isset($allowed[$i->tag]))
				$i->outertext = self::escapeOutput($i->outertext);
			else
				self::replaceTags($i, $disallowed, $disallowedMap, $allowed);
		}
	}
	
	private static function ensureHtmlTagEnd($rt)
	{
		static $selfClosingTags = array
		(
			"area" => true,
			"base" => true,
			"br" => true,
			"col" => true,
			"command" => true,
			"embed" => true,
			"hr" => true,
			"img" => true,
			"input" => true,
			"keygen" => true,
			"link" => true,
			"meta" => true,
			"param" => true,
			"source" => true,
			"track" => true,
			"wbr" => true,
		);
		
		foreach ($rt->find("*") as $i)
		{
			if (Util::isEmpty($i->outertext) || $i->outertext == " :REPLACED: ")
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
					$str = preg_replace('@/\*.*\*/@', "", preg_replace_callback('/\\\([0-9A-Fa-f]{1,6})/i', create_function('$_', '$a = intval($_[1], 16); return $a >= 32 && $a <= 126 ? chr($a) : $_[0];'), $v));
					
					foreach (explode(";", $str) as $j)
					{
						list($k2, $v2) = array_map("trim", explode(":", $j, 2)) + array("", "");
						
						if (preg_match("/b.+havio.+$/i", $k2) ||
							preg_match('/\b(.+[xｘＸ][pｐＰ][rｒＲ].+[sｓＳ][sｓＳ][iｉＩ][oｏＯ].+|data:|javascript:|vbs:|vbscript:)\b/i', $v2))
						{
							$i->$k = null;
							
							break;
						}
					}
				}
				else if ($k == "src" || $k == "href")
					if (preg_match('/(javascript|data|vbs|vbscript):/', $v))
						$i->$k = null;
			}
			
			self::ensureHtmlTagEnd($i);
			
			$outertext = $i->outertext;
			$matches = array();
			
			// close if unclosed tag
			if (strstr($outertext, "/>") != "/>" && strstr($outertext, "</{$i->tag}>") != "</{$i->tag}>")
				$outertext .= "</{$i->tag}>";
			
			// strip any double-closed tags
			if (strpos($i->plaintext, "</") !== false)
			{
				$stack = array();
				$html = "";
				$idx = 0;
				preg_match_all('@<(/?)([^\s>]*).*?(/?)>@i', $outertext, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
				
				if ($matches)
				{
					foreach ($matches as $m)
					{
						$tag = $m[0][0];
						$start = $m[0][1];
						$name = $m[2][0];
						$isClosing = !Util::isEmpty($m[1][0]);
						$isSelfClosed = !Util::isEmpty($m[3][0]);
						$length = strlen($tag);
						
						$html .= substr($outertext, $idx, $start - $idx);
						
						if (!$isSelfClosed)
							if ($isClosing)
								if ($stack && $stack[count($stack) - 1] == $name)
								{
									// closed tag
									$html .= $tag;
									array_pop($stack);
								}
								else
								{
									// double closed tag
									$length = 0;
								}
							else
							{
								// open tag
								
								if (isset($selfClosingTags[$name]))
								{
									$html .= substr($tag, 0, -1) . " />";
									$length += 2;
								}
								else
								{
									$html .= $tag;
								
									array_push($stack, $name);
								}
							}
						
						$idx = $start + $length;
					}
					
					$html .= substr($outertext, $idx);
					
					foreach	($stack as $j)
					{
						// unclosed tag
						$html .= "</{$j}>";
					}
					
					$outertext = $html;
				}
			}
			
			$i->outertext = $outertext;
		}
	}
	
	static function delegateParameters(array $params, array $except = array())
	{
		echo '<input type="hidden" name="encoded" value="true" />';
		
		if ($except)
			echo '<input type="hidden" name="encodedExcept" value="' . Visualizer::escapeOutput(implode(",", $except)) . '" />';
		
		$except = array_flip($except) + array("encoded" => true, "encodedExcept" => true);
		
		foreach ($params as $k => $v)
			if (!isset($except[$k]) && strpos($k, "Auth_") === false)
				echo '<input type="hidden" name="' . Visualizer::escapeOutput($k) . '" value="' . Visualizer::escapeOutput(Util::encodeForOutput(Util::escapeInput($v))) . '" />';
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
		$year = $day * 365;
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
		else if ($diff < $year)
			return date("m/d H:i", $time);
		else
			return date("y/m/d H:i", $time);
	}

	/**
	 * @param string $path
	 * @param int $status
	 * @param string $contentType
	 * @param string $encoding [optional]
	 * @param string $mbencoding [optional]
	 * @return mixed
	 */ 
	static function visualize($path = null, $status = null, $contentType = null, $encoding = null, $mbencoding = null)
	{
		static $nestLevel = 0;
		
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
		
		self::defaultHeaders();
		
		if ($contentType)
			header("Content-Type: {$contentType}");
		else if ($encoding)
			header("Content-Type: text/html; charset={$encoding}");
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
		
		$nestLevel++;
		ob_start();
		eval("?" . ">" . $content);
		$output = ob_get_contents();
		ob_end_clean();
		$nestLevel--;
		
		if ($nestLevel == 0)
		{
			$output = mb_ereg_replace('[\t \r\n]+?<', '<', mb_ereg_replace('>[\t \r\n]+', '>', $output));

			if ($mbencoding)
				$output = mb_convert_encoding($output, $mbencoding, "UTF8");
			
			$output = strtr($output, array
			(
				"<!DOCTYPE html>" => "<!DOCTYPE html>\r\n",
				"__RENDER_TIME__" => round((microtime(true) - $start) * 1000, 2) . "ms",
				"__PROCESS_TIME__" => round(($start - App::$startTime) * 1000, 2) . "ms"
			));
		}
		
		if ($nestLevel == 0)
			self::echoWithCompression($output);
		else
			echo $output;
		
		return true;
	}
	
	/**
	 * @param mixed $obj
	 */
	static function json($obj)
	{
		self::defaultHeaders();
		header("Content-Type: application/json");
		
		Auth::commitSession();
		self::echoWithCompression(json_encode($obj));
		
		return true;
	}
	
	/**
	 * @param mixed $obj
	 */
	static function csv($obj)
	{
		self::defaultHeaders();
		header("Content-Type: text/csv; charset=Shift_JIS; header=present");
		
		$s = fopen("php://output", 'w');
		mb_http_output("Windows-31J");
		
		foreach ($obj as $i)
			fputcsv($s, array_map(create_function('$_', 'return mb_convert_encoding($_, "Windows-31J", "UTF-8");'), $i));
		
		fclose($s);
		
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
	 * @param string $mbencoding
	 * @return mixed
	 */ 
	static function text($content, $encoding = "UTF-8", $mbencoding = null)
	{
		Auth::commitSession();
		
		if (!$mbencoding)
			$mbencoding = $encoding;
		
		mb_http_output($mbencoding);
		self::defaultHeaders();
		header("Content-Type: text/plain; charset={$encoding}");
		self::echoWithCompression(mb_convert_encoding($content, $mbencoding, "UTF-8"));
		
		return true;
	}
	
	private static function echoWithCompression($output)
	{
		if (Configuration::$instance->useOutputCompression &&
			!headers_sent() &&
			isset($_SERVER["HTTP_ACCEPT_ENCODING"]) &&
			in_array("gzip", array_map("trim", explode(",", $_SERVER["HTTP_ACCEPT_ENCODING"]))) &&
			extension_loaded("zlib"))
		{
			header("Content-Encoding: gzip");
			
			$output = gzencode($output);
		}
		
		echo $output;
	}
	
	private static function defaultHeaders()
	{
		header("X-Content-Type-Options: nosniff");
		header("X-Frame-Options: SAMEORIGIN");
		
		$csp = "default-src 'self'; img-src *; script-src 'self' code.jquery.com platform.twitter.com; style-src 'self' code.jquery.com 'unsafe-inline'; frame-src platform.twitter.com";
		
		header("X-Content-Security-Policy: {$csp}");
		
		if (Util::getBrowserType() == Util::BROWSER_TYPE_WEBKIT &&
			strpos($ua = $_SERVER["HTTP_USER_AGENT"], "Safari") !== false &&
			!preg_match('/Version\/[1-5]\./', $ua))
			header("X-WebKit-CSP: {$csp}");
		
		if (Util::getBrowserType() == Util::BROWSER_TYPE_MSIE_NEW)
			header("X-UA-Compatible: IE=8; IE=9");
	}
}

Visualizer::$basePath = rtrim(dirname(mb_strstr(Util::getPhpSelf(), Util::INDEX_FILE_NAME, true) . Util::INDEX_FILE_NAME), "/") . "/";
?>