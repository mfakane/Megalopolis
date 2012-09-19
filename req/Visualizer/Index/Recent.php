<?php
$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;

Visualizer::doctype();
App::load(VISUALIZER_DIR . "Template/Index");
?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<title>
		履歴 - <?+$c->title ?>
	</title>
	<script src="<?+Visualizer::actionHrefArray(array("script", "Index", "Index.js")) ?>"></script>
</head>
<body>
	<? Visualizer::header() ?>
	<div class="entries compact">
		<article>
			<h1>閲覧履歴</h1>
			<?if ($h->entries["view"]): ?>
				<div class="articleBody">
					<? entries($h->entries["view"], false, "compact") ?>
				</div>
			<?else: ?>
				<p class="notify info">
					閲覧履歴はありません
				</p>
			<?endif ?>
		</article>
		<?if ($c->useComments || $c->useAnyPoints()): ?>
			<article>
				<h1>
					<?if ($c->useComments): ?>
						<?if ($c->useAnyPoints()): ?>
							コメント / 評価履歴
						<?else: ?>
							コメント履歴
						<?endif ?>
					<?else: ?>
						評価履歴
					<?endif ?>
				</h1>
				<?if ($h->entries["evaluation"]): ?>
					<div class="articleBody">
						<? entries($h->entries["evaluation"], false, "compact") ?>
					</div>
				<?else: ?>
					<p class="notify info">
						評価履歴はありません
					</p>
				<?endif ?>
			</article>
		<?endif ?>
	</div>
	<? Visualizer::footer() ?>
</body>
</html>