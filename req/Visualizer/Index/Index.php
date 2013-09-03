<?php
$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;

if (App::$actionName == "tag")
	$title = "タグ: {$d}";
else if (App::$actionName == "author")
	$title = "作者: {$d}";
else
	$title = $h->subject == $h->subjectCount ? "最新作品集" : "作品集 {$h->subject}";

$isAdmin = Auth::hasSession(true);
Visualizer::doctype();
App::load(VISUALIZER_DIR . "Template/Index");
?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<?if (App::$actionName == "index"): ?>
		<link href="<?+Visualizer::actionHrefArray(array(0)) ?>.rss" rel="alternate" type="application/rss+xml" title="<?+$c->title ?> 最新作品集 RSS 2.0" />
		<link href="<?+Visualizer::actionHrefArray(array(0)) ?>.atom" rel="alternate" type="application/atom+xml" title="<?+$c->title ?> 最新作品集 Atom" />
		<link href="<?+Visualizer::actionHrefArray(array($h->subject)) ?>.json" rel="alternate" type="application/json" />
		<link href="<?+Visualizer::actionHrefArray(array($h->subject)) ?>" rel="canonical" />
	<?else: ?>
		<link href="<?+Visualizer::actionHrefArray(array(App::$actionName, $d)) ?>.rss" rel="alternate" type="application/rss+xml" title="RSS 2.0" />
		<link href="<?+Visualizer::actionHrefArray(array(App::$actionName, $d)) ?>.atom" rel="alternate" type="application/atom+xml" title="Atom" />
	<?endif ?>
	<link rel="search" href="<?+Visualizer::actionHrefArray(array("search")) ?>" />
	<?if ($h->subjectCount): ?>
		<?if ($h->subject < $h->subjectCount): ?>
			<link rel="prev" href="<?+Visualizer::actionHrefArray(array($h->subject + 1)) ?>" />
		<?endif ?>
		<?if ($h->subject > 1): ?>
			<link rel="next" href="<?+Visualizer::actionHrefArray(array($h->subject - 1)) ?>" />
		<?endif ?>
	<?endif ?>
	<title>
		<?+$title ?> - <?+$c->title ?>
	</title>
	<script src="<?+Visualizer::actionHrefArray(array("script", "Index", "Index.js")) ?>"></script>
</head>
<body class="index">
	<?php
	$arr = array
	(
		"new" => !$c->adminOnly || $isAdmin ? array("新規投稿", "addIcon.png") : null,
		(App::$actionName == "index" ? "random" : implode("/", array(App::$actionName, rawurlencode($d), "random"))) => array("おまかせ表示", "refreshIcon.png"),
		"search" => $c->useSearch ? array("詳細検索", "detailsIcon.png") : null,
		"?visualizer=auto" => Visualizer::isMobile() ? array("携帯表示", "") : null
	);
	
	Visualizer::header
	(
		$title,
		$arr,
		$h->pageCount > 1 ? "{$h->entryCount} 件中 " . (($h->page - 1) * $c->searchPaging + 1) . " - " . (($h->page - 1) * $c->searchPaging + count($h->entries)) .  " 件" : count($h->entries) . " 件"
	);
	?>
	<?if (Visualizer::isSimple()): ?>
		<a id="backToMobile" href="<?+Visualizer::actionHrefArray(array(array("visualizer" => "auto"))) ?>">携帯表示に戻る</a>
	<?endif ?>
	<dl class="status">
		<?if ($h->entryCount > 0): ?>
			<dt>全作品数</dt>
			<dd>
				<?=$h->entryCount ?>
			</dd>
		<?endif ?>
		<dt>最終更新</dt>
		<dd>
			<time datetime="<?+date("c", $h->lastUpdate) ?>">
				<?+Visualizer::formatDateTime($h->lastUpdate) ?>
			</time>
		</dd>
	</dl>
	<?if (App::$actionName == "tag" && (is_array($c->showTweetButton) ? $c->showTweetButton[Configuration::ON_TAG] : $c->showTweetButton)): ?>
		<div class="headdingButtons">
			<? Visualizer::tweetButton(Visualizer::absoluteHref(App::$actionName, $d), $c->tagTweetButtonText, $c->tagTweetButtonHashtags, array
			(
				"[tag]" => $d,
			)) ?>
		</div>
	<?elseif (App::$actionName == "author" && (is_array($c->showTweetButton) ? $c->showTweetButton[Configuration::ON_AUTHOR] : $c->showTweetButton)): ?>
		<div class="headdingButtons">
			<? Visualizer::tweetButton(Visualizer::absoluteHref(App::$actionName, $d), $c->authorTweetButtonText, $c->authorTweetButtonHashtags, array
			(
				"[author]" => $d,
			)) ?>
		</div>
	<?endif ?>
	
	<?if (App::$actionName == "index" && $h->subjectCount > 1): ?>
		<div class="pagerContainer subjectPager">
			<span>作品集: </span>
			<? Visualizer::pager($h->subject, $h->subjectCount, 5, Visualizer::actionHrefArray(array()), true, false, false) ?>
			<form action="<?+Visualizer::actionHrefArray(array()) ?>" method="get">
				<select name="log">
					<?for ($i = $h->subjectCount; $i > 0; $i--): ?>
						<option<?if ($h->subject == $i) echo ' selected="selected"' ?>><?+$i ?></option>
					<?endfor ?>
				</select>
				<button type="submit">
					GO
				</button>
			</form>
		</div>
	<?endif ?>
	<?if (in_array(Util::getBrowserType(), array
	(
		Util::BROWSER_TYPE_MSIE6,
		Util::BROWSER_TYPE_MSIE7,
		Util::BROWSER_TYPE_FIREFOX2,
	))): ?>
		<p class="notify warning">
			古いブラウザを使っているため、表示が乱れたり、ページの一部が使用不能になる可能性があります。最新のバージョンへ変更することを推奨します。
		</p>
	<?endif ?>
	<?if (App::$actionName == "index" && !Util::isEmpty(trim($c->notes))): ?>
		<section class="notes">
			<?=$c->notes ?>
		</section>
	<?endif ?>
	<?if ($h->subjectCount): ?>
		<?if ($h->entries): ?>
			<?if ($isAdmin): ?>
				<form action="" method="post" id="entriesForm">
			<?endif ?>
			<?if ($h->pageCount > 1): ?>
				<?if (App::$actionName == "tag"): ?>
					<? Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHrefArray(array("tag", $d)) . "/") ?>
				<?elseif (App::$actionName == "author"): ?>
					<? Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHrefArray(array("author", $d)) . "/") ?>
				<?endif ?>
			<?endif ?>
			<? entries($h->entries, $isAdmin) ?>
			<?if ($h->pageCount > 1): ?>
				<?if (App::$actionName == "tag"): ?>
					<? Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHrefArray(array("tag", $d)) . "/") ?>
				<?elseif (App::$actionName == "author"): ?>
					<? Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHrefArray(array("author", $d)) . "/") ?>
				<?endif ?>
			<?endif ?>
			<?if ($isAdmin): ?>
					<input type="hidden" name="token" value="<?+$_SESSION[Auth::SESSION_TOKEN] ?>" />
					<section class="admin">
						<ul class="buttons">
							<li>
								<button type="submit" class="unpost" name="admin" value="unpost" id="unpostButton">
									<img src="<?+Visualizer::actionHrefArray(array("style", "deleteButtonIcon.png")) ?>" alt="" />選択した作品を削除
								</button>
							</li>
						</ul>
					</section>
				</form>
			<?endif ?>
		<?elseif (App::$actionName == "index"): ?>
			<p class="notify info">
				この作品集に作品はありません
			</p>
		<?else: ?>
			<p class="notify info">
				一致する作品はありません
			</p>
		<?endif ?>
	<?else: ?>
		<p class="notify info">
			作品集はありません
		</p>
	<?endif ?>
	<? Visualizer::footer() ?>
</body>
</html>