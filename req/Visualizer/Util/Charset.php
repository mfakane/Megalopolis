<?php
namespace Megalopolis;

$c = Configuration::$instance;
/** @var ?array{fullUnicode: bool, converting: bool, maintenance: bool, tables: array{name: string, rows: int, done: bool}[], oldTables: string[]} $d */
$d = Visualizer::$data;
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<link href="<?=Visualizer::escapeOutput(Visualizer::actionHref("style", "convert.css")) ?>" rel="stylesheet" />
	<title>データベースの文字コードの変換 - <?=Visualizer::escapeOutput($c->title) ?></title>
	<script src="<?=Visualizer::escapeOutput(Visualizer::actionHref("script", "Util", "Charset.js")) ?>"></script>
</head>
<body>
	<?php Visualizer::header("データベースの文字コードの変換") ?>
	<section class="convert" id="convert">
		<?php if (is_null($d)): ?>
			<p>
				MySQL 以外のデータベースでは変換は不要です。
			</p>
		<?php elseif ($d["fullUnicode"]): ?>
			<p>
				データベースは utf8mb4 に変換済みです。絵文字などもそのまま保存されます。
			</p>
			<?php if ($d["oldTables"]): ?>
				<form action="<?=Visualizer::escapeOutput(Visualizer::actionHref("util", "charset")) ?>" method="post" id="form" data-action="dropOld">
					<input type="hidden" name="token" value="<?=Visualizer::escapeOutput($_SESSION[Auth::SESSION_TOKEN]) ?>" />
					<p>
						変換前のテーブル (<?=Visualizer::escapeOutput(implode(", ", $d["oldTables"])) ?>) が残っています。<br />
						変換後のデータに問題が無いことを確認してから削除してください。
					</p>
					<p>
						<input type="submit" value="変換前のテーブルを削除" />
					</p>
				</form>
			<?php endif ?>
		<?php else: ?>
			<form action="<?=Visualizer::escapeOutput(Visualizer::actionHref("util", "charset")) ?>" method="post" id="form" data-action="start">
				<input type="hidden" name="token" value="<?=Visualizer::escapeOutput($_SESSION[Auth::SESSION_TOKEN]) ?>" />
				<p>
					データベースのテーブルは utf8 (3 バイト) のため、絵文字などの 4 バイトの文字は数値文字参照に置き換えて保存しています。<br />
					utf8mb4 に変換すると、そのまま保存できるようになります。
				</p>
				<p>
					変換中は投稿・コメント・評価などの書き込みを停止します (閲覧はできます)。<br />
					作品が多い場合は時間がかかります。途中でページを閉じても、もう一度開いて続きから再開できます。<br />
					変換後には検索インデックスを再生成します。<br />
					念のため、事前にデータベースのバックアップを取ってください。
				</p>
				<table>
					<tr><th>テーブル</th><th>行数 (概算)</th><th>状態</th></tr>
					<?php foreach ($d["tables"] as $i): ?>
						<tr>
							<td><?=Visualizer::escapeOutput($i["name"]) ?></td>
							<td><?=Visualizer::escapeOutput($i["rows"]) ?></td>
							<td><?=$i["done"] ? "変換済み" : "" ?></td>
						</tr>
					<?php endforeach ?>
				</table>
				<p>
					<input type="submit" value="<?=$d["converting"] ? "変換を再開" : "変換を開始" ?>" />
					<?php if ($d["converting"]): ?>
						<button type="button" id="abort">変換を中止して書き込みを再開</button>
					<?php endif ?>
				</p>
			</form>
		<?php endif ?>
	</section>
	<?php Visualizer::footer() ?>
</body>
</html>
