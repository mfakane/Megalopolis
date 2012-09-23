<?php
$h = UtilHandler::$instance;
$c = &Configuration::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<meta name="robots" content="noindex,nofollow,noarchive" />
	<title>管理用ツール - <?+$c->title ?></title>
</head>
<body>
	<? Visualizer::header("管理用ツール") ?>
	<ul>
		<?if (Auth::hasSession(true)): ?>
			<li>
				<a href="<?+Visualizer::actionHref("util", "track") ?>">ホスト検索</a>
			</li>
		<?endif ?>
		<?if ($c->utilsEnabled): ?>
			<li>
				<a href="<?+Visualizer::actionHref("util", "hash") ?>">パスワード用ハッシュ算出</a>
			</li>
			<?if (Auth::hasSession(true)): ?>
				<li>
					<a href="<?+Visualizer::actionHref("util", "convert") ?>">Megalith 形式のログの変換</a>
				</li>
				<li>
					<a href="<?+Visualizer::actionHref("util", "convert", "tags") ?>">Megalith 形式のタグ順のインポート</a>
				</li>
				<li>
					<a href="<?+Visualizer::actionHref("util", "reindex") ?>">検索インデックスの再生成</a>
				</li>
			<?endif ?>
		<?endif ?>
	</ul>
	<? Visualizer::footer() ?>
</body>
</html>