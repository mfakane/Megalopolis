<?php
namespace Megalopolis;

$c = &Configuration::$instance;
?>
<?php Visualizer::doctype() ?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<title>エラー</title>
</head>
<body>
	<div data-role="page">
		<header data-role="header" data-backbtn="false">
			<h1>エラー</h1>
		</header>
		<div data-role="content">
			<div class="inset">
				<div>
					<?=Visualizer::escapeOutput(Visualizer::$data->getMessage()) ?>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
