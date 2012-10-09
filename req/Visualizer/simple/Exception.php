<?php
$c = &Configuration::$instance;
?>
<? Visualizer::doctype() ?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<title>エラー</title>
</head>
<body>
	<h1>エラー</h1>
	<p class="content">
		<?+Visualizer::$data->getMessage() ?>
	</p>
	<ul class="menu">
		<a id="menu" name="menu">メニュー</a>
		<li>
			[0]<a href="<?+Visualizer::actionHref() ?>" accesskey="0">トップページへ戻る</a>
		</li>
	</ul>
</body>
</html>