<?php
namespace Megalopolis;

$c = Configuration::$instance;

/** @var \Throwable $exception */
$exception = Visualizer::$data;
?>
<?php Visualizer::doctype() ?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<meta name="robots" content="noindex,nofollow,noarchive" />
	<title>
		エラー
	</title>
</head>
<body>
	<?php Visualizer::header("エラー") ?>
	<p class="notify error">
		<?=Visualizer::escapeOutput($exception->getMessage()) ?>
	</p>
	<?php if ($exception instanceof ApplicationException && isset($exception->data["migration"]) && Auth::hasSession(true)): ?>
		<form class="notify warning" action="<?=Visualizer::escapeOutput(Visualizer::actionHref("util", "migrate")) ?>" method="post">
			<?php if (isset($exception->data["error"])): ?>
				<p>
					前回の更新処理は次のエラーで中断しました: <?=Visualizer::escapeOutput($exception->data["error"]) ?>
				</p>
			<?php endif ?>
			<p>
				データベースのバックアップを取ってから、更新処理を実行してください。データベースが大きい場合は時間がかかります。
			</p>
			<p>
				<input type="hidden" name="token" value="<?=Visualizer::escapeOutput($_SESSION[Auth::SESSION_TOKEN]) ?>" />
				<input type="submit" value="更新処理を実行" />
			</p>
		</form>
	<?php endif ?>
	<?php if ($c->debug): ?>
		<p class="notify error">
			<?php Visualizer::convertedSummary($exception->getTraceAsString()) ?>
		</p>
	<?php endif ?>
	<?php Visualizer::footer() ?>
</body>
</html>
