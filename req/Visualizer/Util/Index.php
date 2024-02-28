<?php
namespace Megalopolis;

$h = UtilHandler::$instance;
$c = &Configuration::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<meta name="robots" content="noindex,nofollow,noarchive" />
	<title>管理用ツール - <?=Visualizer::escapeOutput($c->title) ?></title>
</head>
<body>
	<?php Visualizer::header("管理用ツール") ?>
	<ul>
		<?php if (Auth::hasSession(true)): ?>
			<li>
				<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref("util", "track")) ?>">ホスト検索</a>
			</li>
		<?php endif ?>
		<?php if ($c->utilsEnabled): ?>
			<li>
				<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref("util", "hash")) ?>">パスワード用ハッシュ算出</a>
			</li>
			<?php if (Auth::hasSession(true)): ?>
				<li>
					<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref("util", "convert")) ?>">Megalith 形式のログの変換</a>
				</li>
				<li>
					<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref("util", "convert", "tags")) ?>">Megalith 形式のタグ順のインポート</a>
				</li>
				<li>
					<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref("util", "reindex")) ?>">検索インデックスの再生成</a>
				</li>
			<?php endif ?>
		<?php endif ?>
	</ul>
	<?php Visualizer::footer() ?>
</body>
</html>
