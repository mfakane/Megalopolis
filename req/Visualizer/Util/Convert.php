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
	<title>Megalith 形式のログの変換 - <?=Visualizer::escapeOutput($c->title) ?></title>
	<script src="<?=Visualizer::escapeOutput(Visualizer::actionHref("script", "Util", "Convert.js")) ?>"></script>
</head>
<body>
	<?php Visualizer::header("Megalith 形式のログの変換") ?>
	<section class="convert" id="convert">
		<?php if (is_null($d)): ?>
			<form action="<?=Visualizer::escapeOutput(Visualizer::actionHref("util", "convert")) ?>" method="get" id="form">
				<p>
					ボタンをクリックし、変換を開始します。<br />
					ログが多い場合は、変換に時間がかかります。ご了承ください。
				</p>
				<p>
					<label><input type="checkbox" id="allowOverwrite" name="allowOverwrite" value="yes" />更新日時が変換前と同一の作品の再変換を許可する</label><br />
					<label><input type="checkbox" id="whenNoConvertLineBreakFieldOnly" name="whenNoConvertLineBreakFieldOnly" value="yes" />改行変換フラグが存在しない古いログのみ処理する</label><br />
					<label><input type="checkbox" id="whenContainsWin31JOnly" name="whenContainsWin31JOnly" value="yes" />Windows 拡張文字を含むログのみ処理する</label>
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
