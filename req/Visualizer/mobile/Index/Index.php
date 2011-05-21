<?php
$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;
$basePath = App::$actionName == "index" ? Visualizer::absoluteHref($h->subject) : Visualizer::absoluteHref(App::$actionName, $d);
$m = App::$actionName == "search" ? "s" : (App::$pathInfo ? Util::escapeInput(App::$pathInfo[count(App::$pathInfo) - 1]) : "h");

if (strlen($m) != 1 || intval($m))
	$m = "h";

function makeMenu($basePath, $current)
{
	echo '<ul>';
	
	foreach (array
	(
		"h" => "作品一覧, home",
		"s" => "検索, search",
		"i" => "履歴, history",
		"m" => "その他, more"
	) as $k => $v)
	{
		list($n, $i) = explode(", ", $v);
	?>
		<li>
			<a href="<?+Visualizer::absoluteHref($k) ?>" data-transition="none"<?if ($k == $current): ?> class="ui-btn-active"<?endif ?> data-icon="<?=$i ?>"><?+$n ?></a>
		</li>
	<?php
	}
	
	echo '</ul>';
}

function entries($h, $c)
{
	foreach ($h->entries as $i)
	{
	?>
		<?if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
			<li>
				<a href="<?+Visualizer::absoluteHref($i->subject, $i->id) ?>">
					<h2 class="title"><?+$i->title?></h2>
					<p class="ui-li-aside">
						<?if ($c->showSize[Configuration::ON_SUBJECT]): ?>
							<span class="size"><?+$i->size ?>KB</span>
						<?endif ?>
						<?if ($c->useAnyPoints() && $c->showPoint[Configuration::ON_SUBJECT]): ?>
							<span class="evaluationCount"><?+$i->evaluationCount ?></span>
							<span class="points"><?+$i->points ?></span>
						<?endif ?>
						<?if ($c->useAnyPoints() && $c->showRate[Configuration::ON_SUBJECT]): ?>
							<span class="rate"><?+sprintf("%.2f", $i->rate) ?></span>
						<?endif ?>
						<span class="dateTime"><?+Visualizer::formatShortDateTime($i->dateTime) ?></span>
					</p>
					<?if ($c->showName[Configuration::ON_SUBJECT]): ?>
						<p class="name"><? Visualizer::convertedName($i->name) ?></p>
					<?endif ?>
					<?if ($i->tags && $c->showTags[Configuration::ON_SUBJECT]): ?>
						<ul class="tags">
							<?if ($i->tags): ?>
								<?foreach ($i->tags as $j): ?>
									<li><?+$j ?></li>
								<?endforeach ?>
							<?endif ?>
						</ul>
					<?endif ?>
				</a>
			</li>
		<?endif ?>
	<?php
	}
}

if (App::$actionName == "tag")
	$title = "{$d}";
else if (App::$actionName == "author")
	$title = "{$d}";
else
	$title = $h->subject == $h->subjectCount ? "最新作品集" : "作品集 {$h->subject}";

Visualizer::doctype();
?>
<html manifest="<?+Visualizer::actionHref("manifest") ?>">
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
		<?+$c->title ?>
	</title>
	<script src="<?+Visualizer::actionHref("script", "nehan", "nehan2-min.js") ?>"></script>
	<script src="<?+Visualizer::actionHref("script", "mobile", "Index", "Index.js") ?>"></script>
	<script src="<?+Visualizer::actionHref("script", "mobile", "Read", "Index.js") ?>"></script>
</head>
<body>
	<?if ($m == "h"): ?>
		<div id="home" data-role="page" class="index fulllist">
			<header data-role="header" data-position="fixed" data-backbtn="false">
				<h1><?+$title ?></h1>
				<a href="<?+$basePath ?>/l" data-direction="reverse">作品集</a>
				<a href="#sort" data-transition="slideup">並べ替え</a>
			</header>
			<div data-role="content">
				<ul data-role="listview" class="entries">
					<? entries($h, $c) ?>
				</ul>
			</div>
			<footer data-role="footer" data-position="fixed">
				<div data-role="navbar">
					<? makeMenu($basePath, "h") ?>
				</div>
			</footer>
		</div>
	<?elseif ($m == "s"): ?>
		<form id="search" data-role="page" class="index fulllist" action="<?+Visualizer::actionHref("search") ?>" data-transition="none">
			<header data-role="header" data-backbtn="false">
				<h1>検索</h1>
				<a href="#sort" class="ui-btn-right" data-transition="slideup">並べ替え</a>
				<div class="searchcontainer">
					<input type="search" placeholder="全体を検索" name="query" value="<?+IndexHandler::param("query") ?>" />
				</div>
			</header>
			<div data-role="content">
				<ul data-role="listview" class="entries">
					<?if (IndexHandler::param("query")) entries($h, $c) ?>
					<?if ($h->page < $h->pageCount): ?>
						<li class="nextpage">
							<a href="<?+Visualizer::actionHref("search", array("query" => IndexHandler::param("query"), "p" => $h->page + 1)) ?>">
								次のページ
							</a>
						</li>
					<?endif ?>
				</ul>
			</div>
			<footer data-role="footer" data-position="fixed">
				<div data-role="navbar">
					<? makeMenu($basePath, "s") ?>
				</div>
			</footer>
		</form>
	<?elseif ($m == "i"): ?>
		<div id="history" data-role="page" class="index fulllist">
			<header data-role="header" data-backbtn="false">
				<h1>履歴</h1>
			</header>
			<div data-role="content">
				<ul data-role="listview" class="entries">
					<script>
						megalopolis.index.renderHistory('<?+Visualizer::absoluteHref() ?>');
					</script>
				</ul>
			</div>
			<footer data-role="footer" data-position="fixed">
				<div data-role="navbar">
					<? makeMenu($basePath, "i") ?>
				</div>
			</footer>
		</div>
	<?elseif ($m == "l"): ?>
		<div id="subjects" data-role="page" class="index fulllist">
			<header data-role="header" data-backbtn="false">
				<h1>作品集</h1>
				<a href="<?+Visualizer::absoluteHref($h->subject) ?>" class="ui-btn-right">戻る</a>
			</header>
			<div data-role="content">
				<ul data-role="listview">
					<?foreach (range($h->subjectCount, 1, -1) as $i): ?>
						<li>
							<a href="<?+Visualizer::absoluteHref($i) ?>">
								<h2><?+$i == $h->subjectCount ? "最新作品集" : "作品集 {$i}"?></h2>
							</a>
						</li>
					<?endforeach ?>
				</ul>
			</div>
		</div>
	<?elseif ($m == "m"): ?>
		<div id="more" data-role="page" class="index fulllist">
			<header data-role="header" data-backbtn="false">
				<h1>その他</h1>
			</header>
			<div data-role="content">
				<ul data-role="listview">
					<li>
						<a href="<?+$basePath ?>/n">
							<h2><?+$c->title ?> について</h2>
						</a>
					</li>
					<li>
						<a href="<?+Visualizer::absoluteHref("random") ?>">
							<h2>おまかせ表示</h2>
						</a>
					</li>
					<li>
						<a href="<?+Visualizer::absoluteHref(array("visualizer" => "normal")) ?>" rel="external">
							<h2>PC 版表示</h2>
						</a>
					</li>
				</ul>
			</div>
			<footer data-role="footer" data-position="fixed" data-id="homeTab">
				<div data-role="navbar">
					<? makeMenu($basePath, "m") ?>
				</div>
			</footer>
		</div>
	<?elseif ($m == "n"): ?>
		<div id="more" data-role="page" class="index">
			<header data-role="header" data-backbtn="false">
				<h1>情報</h1>
				<a href="#" data-rel="back">戻る</a>
			</header>
			<div data-role="content">
				<h2><?+$c->title ?></h2>
				<div class="inset">
					<div>
						<?=$c->notes ?>
					</div>
				</div>
			</div>
			<footer data-role="footer" data-position="fixed" data-id="homeTab">
				<div data-role="navbar">
					<? makeMenu($basePath, "m") ?>
				</div>
			</footer>
		</div>
	<?endif ?>
	<?if ($m == "h" || $m == "s"): ?>
		<div id="sort" data-role="page" class="index fulllist">
			<script>
				megalopolis.index.setSortMenu();
			</script>
			<header data-role="header" data-backbtn="false">
				<h1>並べ替え</h1>
				<a href="#" data-rel="back">戻る</a>
			</header>
			<div data-role="content">
				<ul data-role="listview">
					<?if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
						<li>
							<a href="#sort-title">
								<h2>作品名</h2>
							</a>
						</li>
						<?if ($c->showName[Configuration::ON_SUBJECT]): ?>
							<li>
								<a href="#sort-name">
									<h2>作者</h2>
								</a>
							</li>
						<?endif ?>
						<?if ($c->showSize[Configuration::ON_SUBJECT]): ?>
							<li>
								<a href="#sort-size">
									<h2>サイズ</h2>
								</a>
							</li>
						<?endif ?>
						<?if ($c->useAnyPoints()): ?>
							<?if ($c->showPoint[Configuration::ON_SUBJECT]): ?>
								<li>
									<a href="#sort-evaluationCount">
										<h2>評価数</h2>
									</a>
								</li>
								<li>
									<a href="#sort-points">
										<h2>POINT</h2>
									</a>
								</li>
							<?endif ?>
							<?if ($c->showRate[Configuration::ON_SUBJECT]): ?>
								<li>
									<a href="#sort-rate">
										<h2>Rate</h2>
									</a>
								</li>
							<?endif ?>
						<?endif ?>
						<li>
							<a href="#sort-dateTime">
								<h2>投稿日時</h2>
							</a>
						</li>
					<?endif ?>
				</ul>
			</div>
		</div>
	<?endif ?>
</body>
</html>