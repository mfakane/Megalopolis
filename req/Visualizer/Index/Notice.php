<?php
namespace Megalopolis;

$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;

$isAdmin = Auth::hasSession(true);
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<title>
		<?=Visualizer::escapeOutput($c->title) ?>
	</title>
</head>
<body class="index">
	<?php require $d ?>
	<?php Visualizer::footer() ?>
</body>
</html>
