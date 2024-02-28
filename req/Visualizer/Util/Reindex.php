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
	<title>検索インデックスの再生成 - <?=Visualizer::escapeOutput($c->title) ?></title>
	<script src="<?=Visualizer::escapeOutput(Visualizer::actionHref("script", "Util", "Reindex.js")) ?>"></script>
</head>
<body>
	<?php Visualizer::header("検索インデックスの再生成") ?>
	<section class="convert" id="convert">
		<?php if (!is_numeric($d)): ?>
			<form action="<?=Visualizer::escapeOutput(Visualizer::actionHref("util", "reindex")) ?>" method="get" id="form">
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
		<?php else: ?>
			<div>
				<p>
					<?=Visualizer::escapeOutput($d) ?> 作品のインデックス再生成が完了しました。<br />
					<br />
					<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref()) ?>">ホームへ戻る</a>
				</p>
			</div>
		<?php endif ?>
	</section>
	<?php Visualizer::footer() ?>
</body>
</html>
