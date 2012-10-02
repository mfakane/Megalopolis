<?php
$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;
$basePath = Visualizer::absoluteHref();

Visualizer::doctype();
App::load(VISUALIZER_DIR . "mobile/Template/Index");
?>
<html lang="ja">
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
	<div id="history" data-role="page" class="index fulllist">
		<header data-role="header" data-position="fixed" data-backbtn="false">
			<h1>履歴</h1>
		</header>
		<div data-role="content">
			<ul data-role="listview" class="entries">
				<? entries($h->entries["view"], $c) ?>
			</ul>
		</div>
		<footer data-role="footer" data-position="fixed">
			<div data-role="navbar">
				<? makeMenu($basePath, "recent") ?>
			</div>
		</footer>
	</div>
</body>
</html>