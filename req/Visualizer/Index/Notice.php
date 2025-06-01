<?php
namespace Megalopolis;

$c = Configuration::$instance;
$d = Visualizer::$data;

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
