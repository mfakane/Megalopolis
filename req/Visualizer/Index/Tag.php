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
	<? Visualizer::header(App::$actionName == "author" ? "作者の一覧" : "タグの一覧", array
	(
		App::$actionName . "/random" => array("おまかせ表示", "refreshIcon.png")
	)) ?>
	<?if (App::$actionName == "author" && !$c->showName[Configuration::ON_SUBJECT]): ?>
		<p class="notify warning">
			作者の一覧は公開されていません
		</p>
	<?elseif (App::$actionName == "tag" && !$c->showTags[Configuration::ON_SUBJECT]): ?>
		<p class="notify warning">
			タグの一覧は公開されていません
		</p>
	<?else: ?>
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
	<?endif ?>
	<? Visualizer::footer() ?>
</body>
</html>