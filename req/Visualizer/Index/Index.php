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
?>
<html>
<head>
	<? Visualizer::head() ?>
	<?if (App::$actionName == "index"): ?>
		<link href="<?+Visualizer::actionHref(0) ?>.rss" rel="alternate" type="application/rss+xml" title="<?+$c->title ?> 最新作品集 RSS 2.0" />
		<link href="<?+Visualizer::actionHref(0) ?>.atom" rel="alternate" type="application/atom+xml" title="<?+$c->title ?> 最新作品集 Atom" />
		<link href="<?+Visualizer::actionHref($h->subject) ?>.json" rel="alternate" type="application/json" />
	<?else: ?>
		<link href="<?+Visualizer::actionHref(App::$actionName, $d) ?>.rss" rel="alternate" type="application/rss+xml" title="RSS 2.0" />
		<link href="<?+Visualizer::actionHref(App::$actionName, $d) ?>.atom" rel="alternate" type="application/atom+xml" title="Atom" />
	<?endif ?>
	<title>
		<?+$title ?> - <?+$c->title ?>
	</title>
	<script src="<?+Visualizer::actionHref("script", "Index", "Index.js") ?>"></script>
</head>
<body class="index">
	<?php
	$arr = array
	(
		"new, 新規投稿, addIcon.png",
		"random, おまかせ表示, refreshIcon.png",
		"search, 詳細検索, detailsIcon.png",
		Visualizer::isMobile() || Visualizer::isSimple() ? "?visualizer=auto, 携帯表示, " : null
	);
	
	if ($c->adminOnly)
		array_shift($arr);
	
	Visualizer::header($title, $arr, count($h->entries) . " 件");
	?>
	<script>
		megalopolis.index.loadDropDown
		(
			<?=$c->showTitle[Configuration::ON_SUBJECT] ? "true" : "false" ?>,
			<?=$c->showName[Configuration::ON_SUBJECT] ? "true" : "false" ?>,
			<?=$c->showPages[Configuration::ON_SUBJECT] ? "true" : "false" ?>,
			<?=$c->showReadCount[Configuration::ON_SUBJECT] ? "true" : "false" ?>,
			<?=$c->showSize[Configuration::ON_SUBJECT] ? "true" : "false" ?>,
			<?=$c->useAnyPoints() && $c->showRate[Configuration::ON_SUBJECT] ? "true" : "false" ?>,
			<?=$c->useAnyPoints() && $c->showPoint[Configuration::ON_SUBJECT] ? "true" : "false" ?>,
			<?=$c->useAnyPoints() && $c->showRate[Configuration::ON_SUBJECT] ? "true" : "false" ?>
		);
	</script>
	<?if (App::$actionName == "index" && $h->subjectCount > 1): ?>
		<? Visualizer::pager($h->subject, $h->subjectCount, 5, Visualizer::actionHref(), true) ?>
	<?elseif (App::$actionName == "tag" && $h->pageCount > 1): ?>
		<? Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHref("tag", $d) . "/") ?>
	<?elseif (App::$actionName == "author" && $h->pageCount > 1): ?>
		<? Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHref("author", $d) . "/") ?>
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
			<div class="entries<?=$c->listType == Configuration::LIST_SINGLE ? " single" : null ?>">
				<?foreach ($h->entries as $idx => $i): ?><article>
						<?if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
							<h2>
								<?if ($isAdmin): ?>
									<label>
										<input type="checkbox" name="id[]" value="<?+$i->id ?>" />
								<?endif ?>
								<a href="<?+Visualizer::actionHref($i->subject, $i->id) ?>"><?+$i->title ?></a>
								<?if ($isAdmin): ?>
									</label>
								<?endif ?>
							</h2>
						<?endif ?>
						<time pubdate="pubdate" datetime="<?+date("c", $i->dateTime) ?>">
							<?+Visualizer::formatDateTime($i->dateTime) ?>
						</time>
						<?if ($c->showName[Configuration::ON_SUBJECT]): ?>
							<a href="<?+Visualizer::actionHref("author", $i->name) ?>" class="name"><? Visualizer::convertedName($i->name) ?></a>
						<?endif ?>
						<?if ($c->showPages[Configuration::ON_SUBJECT] ||
							  $c->showSize[Configuration::ON_SUBJECT] ||
							  $c->showReadCount[Configuration::ON_SUBJECT] ||
							  $c->useAnyPoints() && ($c->showPoint[Configuration::ON_SUBJECT] || $c->showRate[Configuration::ON_SUBJECT])): ?>
						<dl>
							<?if ($c->showPages[Configuration::ON_SUBJECT]): ?>
								<dt>ページ数</dt>
								<dd class="pageCount">
									<?+$i->pageCount ?>
								</dd>
							<?endif ?>
							<?if ($c->showSize[Configuration::ON_SUBJECT]): ?>
								<dt>サイズ</dt>
								<dd>
									<span class="size"><?+$i->size ?></span>KB
								</dd>
							<?endif ?>
							<?if ($c->showReadCount[Configuration::ON_SUBJECT]): ?>
								<dt>閲覧数</dt>
								<dd class="readCount">
									<?+$i->readCount ?>
								</dd>
							<?endif ?>
							<?if ($c->useAnyPoints()): ?>
								<?if ($c->showPoint[Configuration::ON_SUBJECT]): ?>
									<dt>評価数</dt>
									<dd class="evaluationCount">
										<?+$i->evaluationCount ?>
									</dd>
									<dt>POINT</dt>
									<dd class="points">
										<?+$i->points ?>
									</dd>
								<?endif ?>
								<?if ($c->showRate[Configuration::ON_SUBJECT]): ?>
									<dt>Rate</dt>
									<dd class="rate">
										<?+sprintf("%.2f", $i->rate) ?>
									</dd>
								<?endif ?>
							<?endif ?>
						</dl>
						<?endif ?>
						<?if ($i->tags && $c->showTags[Configuration::ON_SUBJECT]): ?>
							<ul class="tags">
								<?foreach ($i->tags as $j): ?>
									<li>
										<a href="<?+Visualizer::actionHref("tag", $j) ?>"><?+$j ?></a>
									</li>
								<?endforeach ?>
							</ul>
						<?endif ?>
						<?if (!Util::isEmpty($i->summary) && $c->showSummary[Configuration::ON_SUBJECT]): ?>
							<p>
								<? Visualizer::convertedSummary($i->summary) ?>
							</p>
						<?endif ?>
					</article><?endforeach ?>
			</div>
			<?if ($isAdmin): ?>
					<input type="hidden" name="sessionID" value="<?+Auth::getSessionID() ?>" />
					<section class="admin">
						<ul class="buttons">
							<li>
								<button type="submit" class="unpost" name="admin" value="unpost" id="unpostButton">
									<img src="<?+Visualizer::actionHref("style", "deleteButtonIcon.png") ?>" />選択した作品を削除
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