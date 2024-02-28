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
	<h1>エラー</h1>
	<p class="content">
		<?=Visualizer::escapeOutput(Visualizer::$data->getMessage()) ?>
	</p>
	<ul class="menu">
		<a id="menu" name="menu">メニュー</a>
		<li>
			[0]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref()) ?>" accesskey="0">トップページへ戻る</a>
		</li>
	</ul>
</body>
</html>
