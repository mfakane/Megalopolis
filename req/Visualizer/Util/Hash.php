<?php
namespace Megalopolis;

$c = &Configuration::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<title><?=Visualizer::escapeOutput($c->title) ?></title>
</head>
<body>
	<?php Visualizer::header("管理者パスワード用ハッシュ算出") ?>
	<form action="<?=Visualizer::escapeOutput(Visualizer::actionHref(App::$handlerName, "hash")) ?>" method="post">
		<section>
			<input type="text"  name="raw" value="<?=Visualizer::escapeOutput($d ? $d["raw"] : null) ?>" /><br />
			<button type="submit">
				<img src="<?=Visualizer::escapeOutput(Visualizer::actionHref("style", "sendButtonIcon.png")) ?>" alt="" />算出
			</button>
		</section>
		<?php if ($d): ?>
			<p class="notify info">
				<?=Visualizer::escapeOutput($d["hash"]) ?>
			</p>
		<?php endif ?>
	</form>
	<?php Visualizer::footer() ?>
</body>
</html>
