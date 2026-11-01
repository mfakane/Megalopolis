<?php
namespace Megalopolis;

$c = Configuration::$instance;
Visualizer::doctype();

$verb = "";

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
	<?php // スマートフォン向けのスタイルに対応したページから順に viewport を指定する ?>
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,nofollow,noarchive" />
	<title><?=Visualizer::escapeOutput($c->title) ?></title>
</head>
<body class="responsive">
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
