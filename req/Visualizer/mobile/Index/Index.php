<?php
namespace Megalopolis;

require_once __DIR__ . "/../MobileTemplate.php";

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

function sortMenu($h, string $label, string $columnName): void
{
	?>
	<li>
		<a href="#" data-column="<?=Visualizer::escapeOutput($columnName) ?>">
			<h2><?=Visualizer::escapeOutput($label) ?></h2>
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
App::load(Constant::VISUALIZER_DIR . "mobile/Template/Index");
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<?php if (App::$actionName == "index"): ?>
		<link href="<?=Visualizer::escapeOutput(Visualizer::actionHref(0)) ?>.rss" rel="alternate" type="application/rss+xml" title="<?=Visualizer::escapeOutput($c->title) ?> 最新作品集 RSS 2.0" />
		<link href="<?=Visualizer::escapeOutput(Visualizer::actionHref(0)) ?>.atom" rel="alternate" type="application/atom+xml" title="<?=Visualizer::escapeOutput($c->title) ?> 最新作品集 Atom" />
		<link href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->subject)) ?>.json" rel="alternate" type="application/json" />
	<?php else: ?>
		<link href="<?=Visualizer::escapeOutput(Visualizer::actionHref(App::$actionName, $d)) ?>.rss" rel="alternate" type="application/rss+xml" title="RSS 2.0" />
		<link href="<?=Visualizer::escapeOutput(Visualizer::actionHref(App::$actionName, $d)) ?>.atom" rel="alternate" type="application/atom+xml" title="Atom" />
	<?php endif ?>
	<title>
		<?=Visualizer::escapeOutput($c->title) ?>
	</title>
	<script src="<?=Visualizer::escapeOutput(Visualizer::actionHref("script", "mobile", "Index", "Index.js")) ?>"></script>
	<script src="<?=Visualizer::escapeOutput(Visualizer::actionHref("script", "mobile", "Read", "Index.js")) ?>"></script>
</head>
<body>
	<?php if ($m == "h"): ?>
		<div id="home" data-role="page" class="index fulllist">
			<header data-role="header" data-position="fixed" data-backbtn="false">
				<h1><?=Visualizer::escapeOutput($title) ?></h1>
				<a href="<?=Visualizer::escapeOutput($basePath) ?>/l" data-direction="reverse">作品集</a>
				<a href="#sort" data-transition="slideup">並べ替え</a>
			</header>
			<div data-role="content">
				<ul data-role="listview" class="entries">
					<?php MobileTemplate::entries($h->entries ?? [], $c) ?>
				</ul>
			</div>
			<footer data-role="footer" data-position="fixed">
				<div data-role="navbar">
					<?php MobileTemplate::makeMenu($basePath, "h") ?>
				</div>
			</footer>
		</div>
	<?php elseif ($m == "s"): ?>
		<?php if ($c->showTitle[Configuration::ON_SUBJECT] && $c->useSearch): ?>
			<form id="search" data-role="page" class="index fulllist" action="<?=Visualizer::escapeOutput(Visualizer::actionHref("search")) ?>" data-transition="none">
				<header data-role="header" data-backbtn="false">
					<h1>検索</h1>
					<a href="#sort" class="ui-btn-right" data-transition="slideup">並べ替え</a>
					<div class="searchcontainer">
						<input type="search" placeholder="全体を検索" name="query" value="<?=Visualizer::escapeOutput($search) ?>" />
					</div>
					<div data-role="navbar">
						<fieldset data-role="controlgroup" data-type="horizontal" class="items<?=(2 + ($c->showName[Configuration::ON_SUBJECT] ? 1 : 0) + ($c->showTags[Configuration::ON_SUBJECT] ? 1 : 0)) ?>">
							<input type="radio" name="mode" id="modeQuery" value="query"<?=$searchMode == "query" ? ' checked="checked"' : null ?> />
							<label for="modeQuery">全文</label>
							<input type="radio" name="mode" id="modeTitle" value="title"<?=$searchMode == "title" ? ' checked="checked"' : null ?> />
							<label for="modeTitle">作品名</label>
							<?php if ($c->showName[Configuration::ON_SUBJECT]): ?>
								<input type="radio" name="mode" id="modeName" value="name"<?=$searchMode == "name" ? ' checked="checked"' : null ?> />
								<label for="modeName">作者名</label>
							<?php endif ?>
								<?php if ($c->showTags[Configuration::ON_SUBJECT]): ?>
								<input type="radio" name="mode" id="modeTag" value="tag"<?=$searchMode == "tag" ? ' checked="checked"' : null ?> />
								<label for="modeTag">分類タグ</label>
							<?php endif ?>
						</fieldset>
					</div>
				</header>
				<div data-role="content">
					<ul data-role="listview" class="entries">
						<?php if (!Util::isEmpty($search)) MobileTemplate::entries($h->entries ?? [], $c) ?>
						<?php if ($h->page < $h->pageCount): ?>
							<li class="nextpage">
								<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref("search", array("query" => $search, "mode" => $searchMode, "p" => $h->page + 1))) ?>">
									次のページ
								</a>
							</li>
						<?php endif ?>
					</ul>
				</div>
				<footer data-role="footer" data-position="fixed">
					<div data-role="navbar">
						<?php MobileTemplate::makeMenu($basePath, "s") ?>
					</div>
				</footer>
			</form>
		<?php endif ?> 
	<?php elseif ($m == "l"): ?>
		<?php if (isset($_GET["begin"]) && isset($_GET["end"])): ?>
			<?php
			$begin = intval($_GET["begin"]);
			$end = intval($_GET["end"]);
			list($begin, $end) = array(max($begin, $end), min($begin, $end));
			?>
			<div id="subjects" data-role="page" class="index fulllist" data-backbtn="false">
				<header data-role="header">
					<h1><?=Visualizer::escapeOutput($begin) ?>～<?=Visualizer::escapeOutput($end) ?></h1>
					<a href="#" data-rel="back">戻る</a>
				</header>
				<div data-role="content">
					<ul data-role="listview">
						<?php for ($i = $begin; $i >= $end; $i--): ?>
							<li>
								<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($i)) ?>">
									<h2>作品集 <?=Visualizer::escapeOutput($i) ?></h2>
								</a>
							</li>
						<?php endfor ?>
					</ul>
				</div>
			</div>
		<?php else: ?>
			<div id="subjects" data-role="page" class="index fulllist">
				<header data-role="header" data-backbtn="false">
					<h1>作品集</h1>
					<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->subject)) ?>" class="ui-btn-right">戻る</a>
				</header>
				<div data-role="content">
					<ul data-role="listview">
						<?php for ($i = $h->subjectCount; $i > max($h->subjectCount - 5, 0); $i--): ?>
							<li>
								<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($i)) ?>">
									<h2><?=Visualizer::escapeOutput($i == $h->subjectCount ? "最新作品集" : "作品集 {$i}") ?></h2>
								</a>
							</li>
						<?php endfor ?>
						<?php for ($i = $h->subjectCount - 5; $i > 0; $i -= 20): ?>
							<li>
								<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref("l", array("begin" => $i, "end" => max($i - 19, 1)))) ?>">
									<h2><?=Visualizer::escapeOutput("作品集 " . $i . "～" . max($i - 19, 1)) ?></h2>
								</a>
							</li>
						<?php endfor ?>
					</ul>
				</div>
			</div>
		<?php endif ?>
	<?php elseif ($m == "m"): ?>
		<div id="more" data-role="page" class="index fulllist">
			<header data-role="header" data-backbtn="false">
				<h1>その他</h1>
			</header>
			<div data-role="content">
				<ul data-role="listview">
					<li>
						<a href="<?=Visualizer::escapeOutput($basePath) ?>/n">
							<h2><?=Visualizer::escapeOutput($c->title) ?> について</h2>
						</a>
					</li>
					<li>
						<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref("random")) ?>">
							<h2>おまかせ表示</h2>
						</a>
					</li>
					<li>
						<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref(array("visualizer" => "normal"))) ?>" rel="external">
							<h2>PC 版表示</h2>
						</a>
					</li>
					<li>
						<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref(array("visualizer" => "simple"))) ?>" rel="external">
							<h2>携帯表示</h2>
						</a>
					</li>
					<li>
						<a href="<?=Visualizer::escapeOutput($basePath) ?>/c">
							<h2>設定</h2>
						</a>
					</li>
				</ul>
			</div>
			<footer data-role="footer" data-position="fixed" data-id="homeTab">
				<div data-role="navbar">
					<?php MobileTemplate::makeMenu($basePath, "m") ?>
				</div>
			</footer>
		</div>
	<?php elseif ($m == "n"): ?>
		<div id="info" data-role="page" class="index">
			<header data-role="header" data-backbtn="false">
				<h1>情報</h1>
				<a href="#" data-rel="back">戻る</a>
			</header>
			<div data-role="content">
				<h2><?=Visualizer::escapeOutput($c->title) ?></h2>
				<div class="inset">
					<div>
						<?=$c->notes ?>
					</div>
				</div>
			</div>
			<footer data-role="footer" data-position="fixed" data-id="homeTab">
				<div data-role="navbar">
					<?php MobileTemplate::makeMenu($basePath, "m") ?>
				</div>
			</footer>
		</div>
	<?php elseif ($m == "c"): ?>
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
							<select id="verticalSwitch" data-role="slider">
								<option value="no">オフ</option>
								<option value="yes">オン</option>
							</select>
						</div>
					</li>
				</ul>
			</div>
		</div>
	<?php endif ?>
	<div id="sort" data-role="page" class="index fulllist">
		<header data-role="header" data-backbtn="false">
			<h1>並べ替え</h1>
			<a href="#" data-rel="back">戻る</a>
		</header>
		<div data-role="content">
			<ul data-role="listview">
				<?php if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
					<?php sortMenu($h, "作品名", "title") ?>
					<?php if ($c->showName[Configuration::ON_SUBJECT]) sortMenu($h, "作者", "name") ?>
					<?php if ($c->showSize[Configuration::ON_SUBJECT]) sortMenu($h, "サイズ", "size") ?>
					<?php if ($c->showComment[Configuration::ON_SUBJECT]) sortMenu($h, "コメント数", "commentCount") ?>
					<?php if ($c->showPoint[Configuration::ON_SUBJECT]) sortMenu($h, "POINT", "points") ?>
					<?php if ($c->showRate[Configuration::ON_SUBJECT]) sortMenu($h, "Rate", "rate") ?>
					<?php sortMenu($h, "投稿日時", "dateTime") ?>
				<?php endif ?>
			</ul>
		</div>
	</div>
</body>
</html>
