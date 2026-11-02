<?php
namespace Megalopolis;

$c = Configuration::$instance;

/**
 * 設置先からの相対パスを、Web から開くための URL にする
 */
$fileHref = fn(string $path) => Visualizer::$basePath . implode("/", array_map("rawurlencode", explode("/", $path)));

// Web から開けてはいけないファイルを、管理者のブラウザから実際に取得できるか確かめる
// (サーバーから自分自身へは接続できない環境があるため、ブラウザから確かめる)
$exposureProbes = array();

if (Auth::hasSession(true)) {
	$exposureProbes[] = array(
		"href" => $fileHref(Constant::DATA_DIR . "store.txt"),
		"signature" => "*.sqlite",
		"message" => Constant::DATA_DIR . " 以下のファイルが Web から開けます。" . Constant::DATA_DIR . ".htaccess を置くか、サーバの設定でアクセスを拒否してください",
	);

	if ($c->dataStore instanceof SQLiteDataStore) {
		$directory = rtrim($c->dataStore->getDirectory(), "/") . "/";

		// 設置先の外 (../ や絶対パス) に置かれている場合は Web から開けない
		if (!str_starts_with($directory, "/") && !str_starts_with($directory, "../") && !preg_match('/^[A-Za-z]:/', $directory))
			foreach (array("data", App::INDEX_DATABASE) as $name)
				$exposureProbes[] = array(
					"href" => $fileHref(preg_replace('#^(\./)+#', "", $directory) . "{$name}.sqlite"),
					"signature" => "SQLite format 3",
					"message" => "データベース ({$directory}{$name}.sqlite) が Web からダウンロードできます。作品やコメント、接続元の記録などが誰でも取得できる状態です。README.md の「インストール・セットアップ」を参照し、直ちにアクセスを拒否してください",
				);
	}
}

Visualizer::doctype();
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<meta name="robots" content="noindex,nofollow,noarchive" />
	<title>管理用ツール - <?=Visualizer::escapeOutput($c->title) ?></title>
	<?php if ($exposureProbes): ?>
		<script src="<?=Visualizer::escapeOutput(Visualizer::actionHref("script", "Util", "Index.js")) ?>"></script>
	<?php endif ?>
</head>
<body>
	<?php Visualizer::header("管理用ツール") ?>
	<?php if ($exposureProbes): ?>
		<ul class="notify warning" id="exposureWarnings" hidden data-probes="<?=Visualizer::escapeOutput(json_encode($exposureProbes)) ?>"></ul>
	<?php endif ?>
	<?php if (Auth::hasSession(true) && !empty(Visualizer::$data["update"])): ?>
		<p class="notify info" id="updateNotice">
			新しいバージョン <?=Visualizer::escapeOutput(App::VERSION_PREFIX . Visualizer::$data["update"]["version"]) ?> が公開されています (現在 <?=Visualizer::escapeOutput(App::VERSION_PREFIX . App::VERSION) ?>)。
			<a href="<?=Visualizer::escapeOutput(Visualizer::$data["update"]["url"]) ?>" rel="noopener noreferrer">リリースのページへ</a>
		</p>
	<?php endif ?>
	<?php if (Auth::hasSession(true) && !empty(Visualizer::$data["notices"])): ?>
		<ul class="notify warning">
			<?php foreach (Visualizer::$data["notices"] as $i): ?>
				<li>
					<?=Visualizer::escapeOutput($i["message"]) ?>
					<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref("util", $i["action"])) ?>"><?=$i["action"] == "charset" ? "データベースの文字コードの変換へ" : "検索インデックスの再生成へ" ?></a>
				</li>
			<?php endforeach ?>
		</ul>
	<?php endif ?>
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
				<?php if ($c->dataStore instanceof MySQLDataStore): ?>
					<li>
						<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref("util", "charset")) ?>">データベースの文字コードの変換 (utf8mb4)</a>
					</li>
				<?php endif ?>
			<?php endif ?>
		<?php endif ?>
	</ul>
	<?php Visualizer::footer() ?>
</body>
</html>
