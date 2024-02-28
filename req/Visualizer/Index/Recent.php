<?php
namespace Megalopolis;

require_once __DIR__ . "/../Template.php";

$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;

Visualizer::doctype();
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<title>
		履歴 - <?=Visualizer::escapeOutput($c->title) ?>
	</title>
	<script src="<?=Visualizer::escapeOutput(Visualizer::actionHrefArray(array("script", "Index", "Index.js"))) ?>"></script>
</head>
<body>
	<?php Visualizer::header() ?>
	<div class="entries compact">
		<article>
			<h1>閲覧履歴</h1>
			<?php if (isset($h->recentEntries["view"])): ?>
				<div class="articleBody">
					<?php Template::entries($h->recentEntries["view"], false, "compact") ?>
				</div>
			<?php else: ?>
				<p class="notify info">
					閲覧履歴はありません
				</p>
			<?php endif ?>
		</article>
		<?php if ($c->useComments || $c->useAnyPoints()): ?>
			<article>
				<h1>
					<?php if ($c->useComments): ?>
						<?php if ($c->useAnyPoints()): ?>
							コメント / 評価履歴
						<?php else: ?>
							コメント履歴
						<?php endif ?>
					<?php else: ?>
						評価履歴
					<?php endif ?>
				</h1>
				<?php if (isset($h->recentEntries["evaluation"])): ?>
					<div class="articleBody">
						<?php Template::entries($h->recentEntries["evaluation"], false, "compact") ?>
					</div>
				<?php else: ?>
					<p class="notify info">
						評価履歴はありません
					</p>
				<?php endif ?>
			</article>
		<?php endif ?>
	</div>
	<?php Visualizer::footer() ?>
</body>
</html>
