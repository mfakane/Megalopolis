<?php
namespace Megalopolis;

require_once __DIR__ . "/../MobileTemplate.php";

$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;
$basePath = Visualizer::absoluteHref();

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
	<div id="history" data-role="page" class="index fulllist">
		<header data-role="header" data-position="fixed" data-backbtn="false">
			<h1>履歴</h1>
		</header>
		<div data-role="content">
			<ul data-role="listview" class="entries">
				<?php MobileTemplate::entries($h->recentEntries["view"] ?? [], $c) ?>
			</ul>
		</div>
		<footer data-role="footer" data-position="fixed">
			<div data-role="navbar">
				<?php MobileTemplate::makeMenu($basePath, "recent") ?>
			</div>
		</footer>
	</div>
</body>
</html>
