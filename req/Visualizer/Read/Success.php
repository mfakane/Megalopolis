<?php
namespace Megalopolis;

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
	<?php Visualizer::head() ?>
	<meta name="robots" content="noindex,nofollow,noarchive" />
	<title><?=Visualizer::escapeOutput($c->title) ?></title>
</head>
<body>
	<?php Visualizer::header("完了") ?>
	<section class="success">
		<p>
			<?=Visualizer::escapeOutput($verb) ?>に成功しました
		</p>
		<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref()) ?>">ホームへ戻る</a>
	</section>
	<?php Visualizer::footer() ?>
</body>
</html>
