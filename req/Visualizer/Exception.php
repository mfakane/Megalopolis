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
	<?php if ($c->debug): ?>
		<p class="notify error">
			<?php Visualizer::convertedSummary($exception->getTraceAsString()) ?>
		</p>
	<?php endif ?>
	<?php Visualizer::footer() ?>
</body>
</html>
