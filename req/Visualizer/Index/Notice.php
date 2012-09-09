<?php
$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;

$isAdmin = Auth::hasSession(true);
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<title>
		<?+$c->title ?>
	</title>
</head>
<body class="index">
	<? require $d ?>
	<? Visualizer::footer() ?>
</body>
</html>