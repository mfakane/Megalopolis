<?php
$c = &Configuration::$instance;
?>
<? Visualizer::doctype() ?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<meta name="robots" content="noindex,nofollow,noarchive" />
	<title>
		エラー
	</title>
</head>
<body>
	<? Visualizer::header("エラー") ?>
	<p class="notify error">
		<?+Visualizer::$data->getMessage() ?>
	</p>
	<?if ($c->debug): ?>
		<p class="notify error">
			<? Visualizer::convertedSummary(Visualizer::$data->getTraceAsString()) ?>
		</p>
	<?endif ?>
	<? Visualizer::footer() ?>
</body>
</html>