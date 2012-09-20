<?php
$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;
$basePath = App::$actionName == "index" ? Visualizer::absoluteHref($h->subject) : Visualizer::absoluteHref(App::$actionName, $d);
$searchMode = "query";
$search = "";

if (App::$actionName == "search")
	if (!is_null($search = IndexHandler::param("query")))
		$searchMode = "query";
	else if (!is_null($search = IndexHandler::param("title")))
		$searchMode = "title";
	else if (!is_null($search = IndexHandler::param("name")))
		$searchMode = "name";
	else if (!is_null($search = IndexHandler::param("tag")))
		$searchMode = "tag";

global $m;

$m = App::$actionName == "search" ? "s" : (App::$pathInfo ? Util::escapeInput(App::$pathInfo[count(App::$pathInfo) - 1]) : "h");

if (!Util::isLength($m, 1) || intval($m))
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
			<a href="<?+$basePath . "/" . $k ?>" data-transition="none"<?if ($k == $current): ?> class="ui-btn-active"<?endif ?> data-icon="<?=$i ?>"><?+$n ?></a>
		</li>
	<?php
	}
	
	echo '</ul>';
}

function entryInfo($columnName, $value)
{
	?>
	<span class="<?=$columnName ?>"><?+$value ?></span>
	<?php
}

function entries($h, $c)
{
	if (!$h->entries)
		return;
	
	foreach ($h->entries as $i)
	{
	?>
		<?if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
			<li>
				<a href="<?+Visualizer::actionHref($i->subject, $i->id) ?>">
					<h2 class="title"><?+$i->title?></h2>
					<p class="ui-li-aside">
						<? entryInfo("dateTime", Visualizer::formatShortDateTime($i->dateTime)) ?><br />
						<?if ($c->showSize[Configuration::ON_SUBJECT]) entryInfo("size", "{$i->size}KB") ?>
						<?if ($c->showComment[Configuration::ON_SUBJECT]) entryInfo("commentCount", $i->commentCount) ?>
						<?if ($c->showPoint[Configuration::ON_SUBJECT]) entryInfo("evaluationCount", $c->pointMap && $c->commentPointMap ? "{$i->commentedEvaluationCount}/{$i->evaluationCount}" : $i->evaluationCount) ?>
						<?if ($c->showPoint[Configuration::ON_SUBJECT]) entryInfo("points", $i->points) ?>
						<?if ($c->showRate[Configuration::ON_SUBJECT]) entryInfo("rate", sprintf("%.2f", $i->rate)) ?>
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

function sortMenu($h, $label, $columnName)
{
	?>
	<li>
		<a href="javascript:void(0);" onclick="megalopolis.index.setSort('<?+$columnName ?>'); history.back();">
			<h2><?+$label ?></h2>
		</a>
	</li>
	<?php
}

if (App::$actionName == "tag")
	$title = "{$d}";
else if (App::$actionName == "author")
	$title = "{$d}";
else
	$title = $h->subject == $h->subjectCount ? "最新作品集" : "作品集 {$h->subject}";

Visualizer::doctype();
?>
<html lang="ja" manifest="<?+Visualizer::actionHref("manifest") ?>">
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
					<input type="search" placeholder="全体を検索" name="query" value="<?+$search ?>" />
				</div>
				<?if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
					<div data-role="navbar">
						<fieldset data-role="controlgroup" data-type="horizontal" class="items<?=(2 + $c->showName[Configuration::ON_SUBJECT] + $c->showTags[Configuration::ON_SUBJECT]) ?>">
						     	<input type="radio" name="mode" id="modeQuery" value="query"<?=$searchMode == "query" ? ' checked="checked"' : null ?> />
						     	<label for="modeQuery">全文</label>
						     	<input type="radio" name="mode" id="modeTitle" value="title"<?=$searchMode == "title" ? ' checked="checked"' : null ?> />
						     	<label for="modeTitle">作品名</label>
						     	<?if ($c->showName[Configuration::ON_SUBJECT]): ?>
							     	<input type="radio" name="mode" id="modeName" value="name"<?=$searchMode == "name" ? ' checked="checked"' : null ?> />
							     	<label for="modeName">作者名</label>
						     	<?endif ?>
								<?if ($c->showTags[Configuration::ON_SUBJECT]): ?>
							     	<input type="radio" name="mode" id="modeTag" value="tag"<?=$searchMode == "tag" ? ' checked="checked"' : null ?> />
							     	<label for="modeTag">分類タグ</label>
						     	<?endif ?>
						</fieldset>
					</div>
				<?endif ?>
			</header>
			<div data-role="content">
				<ul data-role="listview" class="entries">
					<?if (!Util::isEmpty($search)) entries($h, $c) ?>
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
						megalopolis.index.renderHistory('<?+Visualizer::actionHref() ?>');
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
				<a href="<?+Visualizer::actionHref($h->subject) ?>" class="ui-btn-right">戻る</a>
			</header>
			<div data-role="content">
				<ul data-role="listview">
					<?foreach (range($h->subjectCount, 1, -1) as $i): ?>
						<li>
							<a href="<?+Visualizer::actionHref($i) ?>">
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
						<a href="<?+Visualizer::actionHref("random") ?>">
							<h2>おまかせ表示</h2>
						</a>
					</li>
					<li>
						<a href="<?+Visualizer::actionHref(array("visualizer" => "normal")) ?>" rel="external">
							<h2>PC 版表示</h2>
						</a>
					</li>
					<li>
						<a href="<?+Visualizer::actionHref(array("visualizer" => "simple")) ?>" rel="external">
							<h2>携帯表示</h2>
						</a>
					</li>
					<li>
						<a href="<?+$basePath ?>/c">
							<h2>設定</h2>
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
		<div id="info" data-role="page" class="index">
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
	<?elseif ($m == "c"): ?>
		<div id="config" data-role="page" class="index">
			<header data-role="header" data-backbtn="false">
				<h1>設定</h1>
				<a href="#" data-rel="back">戻る</a>
			</header>
			<div data-role="content">
				<ul data-role="listview" data-inset="true">
					<li>
						<div data-role="fieldcontain">
							<label for="verticalSwitch">縦書き</label>
							<select id="verticalSwitch" data-role="slider" onchange="megalopolis.index.settingsChanged()">
								<option value="no">オフ</option>
								<option value="yes">オン</option>
							</select>
						</div>
					</li>
				</ul>
			</div>
		</div>
	<?endif ?>
	<div id="sort" data-role="page" class="index fulllist">
		<header data-role="header" data-backbtn="false">
			<h1>並べ替え</h1>
			<a href="#" data-rel="back">戻る</a>
		</header>
		<div data-role="content">
			<ul data-role="listview">
				<?if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
					<? sortMenu($h, "作品名", "title") ?>
					<?if ($c->showName[Configuration::ON_SUBJECT]) sortMenu($h, "作者", "name") ?>
					<?if ($c->showSize[Configuration::ON_SUBJECT]) sortMenu($h, "サイズ", "size") ?>
					<?if ($c->showComment[Configuration::ON_SUBJECT]) sortMenu($h, "コメント数", "commentCount") ?>
					<?if ($c->showPoint[Configuration::ON_SUBJECT]): ?>
						<? sortMenu($h, "評価数", "evaluationCount") ?>
						<? sortMenu($h, "POINT", "points") ?>
					<?endif ?>
					<?if ($c->showRate[Configuration::ON_SUBJECT]) sortMenu($h, "Rate", "rate") ?>
					<? sortMenu($h, "投稿日時", "dateTime") ?>
				<?endif ?>
			</ul>
		</div>
	</div>
</body>
</html>