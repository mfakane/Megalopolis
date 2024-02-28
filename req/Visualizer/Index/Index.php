<?php
namespace Megalopolis;

require_once __DIR__ . "/../Template.php";

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
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<?php if (App::$actionName == "index"): ?>
		<link href="<?=Visualizer::escapeOutput(Visualizer::actionHrefArray(array(0))) ?>.rss" rel="alternate" type="application/rss+xml" title="<?=Visualizer::escapeOutput($c->title) ?> 最新作品集 RSS 2.0" />
		<link href="<?=Visualizer::escapeOutput(Visualizer::actionHrefArray(array(0))) ?>.atom" rel="alternate" type="application/atom+xml" title="<?=Visualizer::escapeOutput($c->title) ?> 最新作品集 Atom" />
		<link href="<?=Visualizer::escapeOutput(Visualizer::actionHrefArray(array($h->subject))) ?>.json" rel="alternate" type="application/json" />
		<link href="<?=Visualizer::escapeOutput(Visualizer::actionHrefArray(array($h->subject))) ?>" rel="canonical" />
	<?php else: ?>
		<link href="<?=Visualizer::escapeOutput(Visualizer::actionHrefArray(array(App::$actionName, $d))) ?>.rss" rel="alternate" type="application/rss+xml" title="RSS 2.0" />
		<link href="<?=Visualizer::escapeOutput(Visualizer::actionHrefArray(array(App::$actionName, $d))) ?>.atom" rel="alternate" type="application/atom+xml" title="Atom" />
	<?php endif ?>
	<link rel="search" href="<?=Visualizer::escapeOutput(Visualizer::actionHrefArray(array("search"))) ?>" />
	<?php if ($h->subjectCount): ?>
		<?php if ($h->subject < $h->subjectCount): ?>
			<link rel="prev" href="<?=Visualizer::escapeOutput(Visualizer::actionHrefArray(array($h->subject + 1))) ?>" />
		<?php endif ?>
		<?php if ($h->subject > 1): ?>
			<link rel="next" href="<?=Visualizer::escapeOutput(Visualizer::actionHrefArray(array($h->subject - 1))) ?>" />
		<?php endif ?>
	<?php endif ?>
	<title>
		<?=Visualizer::escapeOutput($title) ?> - <?=Visualizer::escapeOutput($c->title) ?>
	</title>
	<script src="<?=Visualizer::escapeOutput(Visualizer::actionHrefArray(array("script", "Index", "Index.js"))) ?>"></script>
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
		$h->pageCount > 1 ? "{$h->entryCount} 件中 " . (($h->page - 1) * $c->searchPaging + 1) . " - " . (($h->page - 1) * $c->searchPaging + count($h->entries ?? [])) .  " 件" : count($h->entries ?? []) . " 件"
	);
	?>
	<?php if (Visualizer::isSimple()): ?>
		<a id="backToMobile" href="<?=Visualizer::escapeOutput(Visualizer::actionHrefArray(array(array("visualizer" => "auto")))) ?>">携帯表示に戻る</a>
	<?php endif ?>
	<dl class="status">
		<?php if ($h->entryCount > 0): ?>
			<dt>全作品数</dt>
			<dd>
				<?=$h->entryCount ?>
			</dd>
		<?php endif ?>
		<?php if ($h->lastUpdate): ?>
			<dt>最終更新</dt>
			<dd>
				<time datetime="<?=Visualizer::escapeOutput(date("c", $h->lastUpdate)) ?>">
					<?=Visualizer::escapeOutput(Visualizer::formatDateTime($h->lastUpdate)) ?>
				</time>
			</dd>
		<?php endif ?>
	</dl>
	<?php if (App::$actionName == "tag" && (is_array($c->showTweetButton) ? $c->showTweetButton[Configuration::ON_TAG] : $c->showTweetButton)): ?>
		<div class="headdingButtons">
			<?php Visualizer::tweetButton(Visualizer::absoluteHref(App::$actionName, $d), $c->tagTweetButtonText, $c->tagTweetButtonHashtags, array
			(
				"[tag]" => $d,
			)) ?>
		</div>
	<?php elseif (App::$actionName == "author" && (is_array($c->showTweetButton) ? $c->showTweetButton[Configuration::ON_AUTHOR] : $c->showTweetButton)): ?>
		<div class="headdingButtons">
			<?php Visualizer::tweetButton(Visualizer::absoluteHref(App::$actionName, $d), $c->authorTweetButtonText, $c->authorTweetButtonHashtags, array
			(
				"[author]" => $d,
			)) ?>
		</div>
	<?php endif ?>
	
	<?php if (App::$actionName == "index" && $h->subjectCount > 1): ?>
		<div class="pagerContainer subjectPager">
			<span>作品集: </span>
			<?php Visualizer::pager($h->subject, $h->subjectCount, 5, Visualizer::actionHrefArray(array()), true, false, false) ?>
			<form action="<?=Visualizer::escapeOutput(Visualizer::actionHrefArray(array())) ?>" method="get">
				<select name="log">
					<?php for ($i = $h->subjectCount; $i > 0; $i--): ?>
						<option<?php if ($h->subject == $i) echo ' selected="selected"' ?>><?=Visualizer::escapeOutput($i) ?></option>
					<?php endfor ?>
				</select>
				<button type="submit">
					GO
				</button>
			</form>
		</div>
	<?php endif ?>
	<?php if (in_array(Util::getBrowserType(), array
	(
		Util::BROWSER_TYPE_MSIE6,
		Util::BROWSER_TYPE_MSIE7,
		Util::BROWSER_TYPE_FIREFOX2,
	))): ?>
		<p class="notify warning">
			古いブラウザを使っているため、表示が乱れたり、ページの一部が使用不能になる可能性があります。最新のバージョンへ変更することを推奨します。
		</p>
	<?php endif ?>
	<?php if (App::$actionName == "index" && !Util::isEmpty(trim($c->notes ?? ""))): ?>
		<section class="notes">
			<?=$c->notes ?>
		</section>
	<?php endif ?>
	<?php if ($h->subjectCount): ?>
		<?php if ($h->entries): ?>
			<?php if ($isAdmin): ?>
				<form action="" method="post" id="entriesForm">
			<?php endif ?>
			<?php if ($h->pageCount > 1): ?>
				<?php if (App::$actionName == "tag"): ?>
					<?php Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHrefArray(array("tag", $d)) . "/") ?>
				<?php elseif (App::$actionName == "author"): ?>
					<?php Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHrefArray(array("author", $d)) . "/") ?>
				<?php endif ?>
			<?php endif ?>
			<?php Template::entries($h->entries, $isAdmin) ?>
			<?php if ($h->pageCount > 1): ?>
				<?php if (App::$actionName == "tag"): ?>
					<?php Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHrefArray(array("tag", $d)) . "/") ?>
				<?php elseif (App::$actionName == "author"): ?>
					<?php Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHrefArray(array("author", $d)) . "/") ?>
				<?php endif ?>
			<?php endif ?>
			<?php if ($isAdmin): ?>
					<input type="hidden" name="token" value="<?=Visualizer::escapeOutput($_SESSION[Auth::SESSION_TOKEN]) ?>" />
					<section class="admin">
						<ul class="buttons">
							<li>
								<button type="submit" class="unpost" name="admin" value="unpost" id="unpostButton">
									<img src="<?=Visualizer::escapeOutput(Visualizer::actionHrefArray(array("style", "deleteButtonIcon.png"))) ?>" alt="" />選択した作品を削除
								</button>
							</li>
						</ul>
					</section>
				</form>
			<?php endif ?>
		<?php elseif (App::$actionName == "index"): ?>
			<p class="notify info">
				この作品集に作品はありません
			</p>
		<?php else: ?>
			<p class="notify info">
				一致する作品はありません
			</p>
		<?php endif ?>
	<?php else: ?>
		<p class="notify info">
			作品集はありません
		</p>
	<?php endif ?>
	<?php Visualizer::footer() ?>
</body>
</html>
