<?php
$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();
?>
<html>
<head>
	<? Visualizer::head() ?>
	<title>
		<?+App::$actionName == "author" ? "作者" : "タグ" ?>の一覧 - <?+$c->title ?>
	</title>
</head>
<body>
	<? Visualizer::header(App::$actionName == "author" ? "作者の一覧" : "タグの一覧", App::$actionName == "author"
		? array
		(
			", トップに戻る, returnIcon.png"
		)
		: array
		(
			", トップに戻る, returnIcon.png",
			"tag/random, おまかせ表示, refreshIcon.png"
		)
	) ?>
	<? Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHref(App::$actionName)) ?>
	<?if ($d): ?>
		<ul class="taglist">
			<?foreach ($d as $k => $v): ?>
				<li>
					<a href="<?+Visualizer::actionHref(App::$actionName, $k) ?>"><?+$k ?><span class="count"><?+$v ?></span></a>
				</li>
			<?endforeach ?>
		</ul>
	<?else: ?>
		<p class="notify info">
			<?+App::$actionName == "author" ? "作者はいません" : "タグはありません" ?>
		</p>
	<?endif ?>
	<? Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHref(App::$actionName)) ?>
	<? Visualizer::footer() ?>
</body>
</html>