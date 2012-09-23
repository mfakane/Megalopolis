<?php
$h = ReadHandler::$instance;
$c = &Configuration::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();

switch (App::$actionName)
{
	case "post":
		$verb = "投稿";
	
		break;
	case "comment":
		$verb = "コメント";
		
		break;
	case "evaluate":
		$verb = "評価";
		
		break;
}
?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<meta name="robots" content="noindex,nofollow,noarchive" />
	<title><?+$c->title ?></title>
</head>
<body>
	<? Visualizer::header("完了") ?>
	<section class="success">
		<p>
			<?+$verb ?>に成功しました
		</p>
		<a href="<?+Visualizer::actionHref() ?>">ホームへ戻る</a>
	</section>
	<? Visualizer::footer() ?>
</body>
</html>