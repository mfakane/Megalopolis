<?php
$h = UtilHandler::$instance;
$c = &Configuration::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<title>管理用ツール - <?+$c->title ?></title>
</head>
<body>
	<? Visualizer::header("管理用ツール") ?>
	<?if (!$c->utilsEnabled): ?>
		<p class="notify warning">
			管理用ツールは無効化されています
		</p>
	<?else: ?>
		<ul>
			<li>
				<a href="<?+Visualizer::actionHref("util", "hash") ?>">パスワード用ハッシュ算出</a>
			</li>
			<?if (Auth::hasSession(true)): ?>
				<li>
					<a href="<?+Visualizer::actionHref("util", "convert") ?>">Megalith 形式のログの変換</a>
				</li>
				<li>
					<a href="<?+Visualizer::actionHref("util", "reindex") ?>">検索インデックスの再生成</a>
				</li>
			<?endif ?>
		</ul>
	<?endif ?>
	<? Visualizer::footer() ?>
</body>
</html>