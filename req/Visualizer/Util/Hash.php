<?php
$c = &Configuration::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<title><?+$c->title ?></title>
</head>
<body>
	<? Visualizer::header("管理者パスワード用ハッシュ算出") ?>
	<form action="<?+Visualizer::actionHref(App::$handlerName, "hash") ?>" method="post">
		<section>
			<input type="text"  name="raw" value="<?+$d ? $d["raw"] : null ?>" /><br />
			<button type="submit">
				<img src="<?+Visualizer::actionHref("style", "sendButtonIcon.png") ?>" alt="" />算出
			</button>
		</section>
		<?if ($d): ?>
			<p class="notify info">
				<?+$d["hash"] ?>
			</p>
		<?endif ?>
	</form>
	<? Visualizer::footer() ?>
</body>
</html>