<?php
namespace Megalopolis;

$h = ReadHandler::$instance;
$c = &Configuration::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();

switch (App::$actionName)
{
	case "comment":
		$verb = "作品にコメント";
		
		break;
	case "evaluate":
		$verb = "作品を評価";
		
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
	<h1>
		<?=Visualizer::escapeOutput($h->entry->title) ?>
	</h1>
	<div class="content">
		<p>
			<?=Visualizer::escapeOutput($verb) ?>しました
		</p>
	</div>
	<ul class="menu">
		<a id="menu" name="menu">メニュー</a>
		<li>
			[3]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->entry->subject, $h->entry->id, "c")) ?>" accesskey="3">コメントを見る</a>
		</li>
		<li>
			[4]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->entry->subject, $h->entry->id)) ?>" accesskey="4">本文へ戻る</a>
		</li>
		<li>
			[0]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->entry->subject)) ?>" accesskey="0">作品集へ戻る</a>
		</li>
	</ul>
</body>
</html>
