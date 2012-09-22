<?php
$c = &Configuration::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<link href="<?+Visualizer::actionHref("style", "convert.css") ?>" rel="stylesheet" />
	<title>検索インデックスの再生成 - <?+$c->title ?></title>
	<script src="<?+Visualizer::actionHref("script", "Util", "Reindex.js") ?>"></script>
</head>
<body>
	<? Visualizer::header("検索インデックスの再生成") ?>
	<section class="convert" id="convert">
		<?if (!is_numeric($d)): ?>
			<form action="<?+Visualizer::actionHref("util", "reindex") ?>" method="get" id="form">
				<p>
					ボタンをクリックし、検索インデックスの再生成を開始します。<br />
					作品が多い場合は、再生成に時間がかかります。ご了承ください。
				</p>
				<p>
					<label><input type="checkbox" id="force" name="force" value="yes" checked="checked" />既存の検索インデックスをクリアしてから再生成する</label>
				</p>
				<p>
					<input type="submit" value="再生成開始" />
					<input type="hidden" name="p" value="list" />
				</p>
			</form>
		<?else: ?>
			<div>
				<p>
					<?+$d ?> 作品のインデックス再生成が完了しました。<br />
					<br />
					<a href="<?+Visualizer::actionHref() ?>">ホームへ戻る</a>
				</p>
			</div>
		<?endif ?>
	</section>
	<? Visualizer::footer() ?>
</body>
</html>