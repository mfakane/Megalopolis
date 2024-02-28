<?php
namespace Megalopolis;

$c = &Configuration::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<link href="<?=Visualizer::escapeOutput(Visualizer::actionHref("style", "convert.css")) ?>" rel="stylesheet" />
	<title>Megalith 形式のタグ順のインポート - <?=Visualizer::escapeOutput($c->title) ?></title>
	<script src="<?=Visualizer::escapeOutput(Visualizer::actionHref("script", "Util", "Convert", "Tags.js")) ?>"></script>
</head>
<body>
	<?php Visualizer::header("Megalith 形式のタグ順のインポート") ?>
	<section class="convert" id="convert">
		<?php if (is_null($d)): ?>
			<form action="<?=Visualizer::escapeOutput(Visualizer::actionHref("util", "convert", "tags")) ?>" method="get" id="form">
				<p>
					ボタンをクリックし、タグの順番のインポートを開始します。<br />
					ログが多い場合は、変換に時間がかかります。ご了承ください。
				</p>
				<p>
					<input type="submit" value="変換開始" />
					<input type="hidden" name="p" value="list" />
				</p>
			</form>
		<?php else: ?>
			<div>
				<p>
					<?=Visualizer::escapeOutput($d) ?> 作品の変換が完了しました。<br />
					<br />
					<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref()) ?>">ホームへ戻る</a>
				</p>
			</div>
		<?php endif ?>
	</section>
	<?php Visualizer::footer() ?>
</body>
</html>
