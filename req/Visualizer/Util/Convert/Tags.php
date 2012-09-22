<?php
$c = &Configuration::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<link href="<?+Visualizer::actionHref("style", "convert.css") ?>" rel="stylesheet" />
	<title>Megalith 形式のタグ順のインポート - <?+$c->title ?></title>
	<script src="<?+Visualizer::actionHref("script", "Util", "Convert", "Tags.js") ?>"></script>
</head>
<body>
	<? Visualizer::header("Megalith 形式のタグ順のインポート") ?>
	<section class="convert" id="convert">
		<?if (is_null($d)): ?>
			<form action="<?+Visualizer::actionHref("util", "convert", "tags") ?>" method="get" id="form">
				<p>
					ボタンをクリックし、タグの順番のインポートを開始します。<br />
					ログが多い場合は、変換に時間がかかります。ご了承ください。
				</p>
				<p>
					<input type="submit" value="変換開始" />
					<input type="hidden" name="p" value="list" />
				</p>
			</form>
		<?else: ?>
			<div>
				<p>
					<?+$d ?> 作品の変換が完了しました。<br />
					<br />
					<a href="<?+Visualizer::actionHref() ?>">ホームへ戻る</a>
				</p>
			</div>
		<?endif ?>
	</section>
	<? Visualizer::footer() ?>
</body>
</html>