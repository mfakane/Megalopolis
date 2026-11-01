<?php
namespace Megalopolis;

$c = Configuration::$instance;
$d = Visualizer::$data;

Visualizer::doctype();
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<?php // スマートフォン向けのスタイルに対応したページから順に viewport を指定する ?>
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>
		<?=Visualizer::escapeOutput($c->title) ?>
	</title>
</head>
<body class="index responsive">
	<?php require $d ?>
	<?php Visualizer::footer() ?>
</body>
</html>
