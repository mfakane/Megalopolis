<?php
namespace Megalopolis;

$c = Configuration::$instance;
$d = Visualizer::$data;
?>
<?php Visualizer::doctype() ?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<?php // スマートフォン向けのスタイルに対応したページから順に viewport を指定する ?>
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,nofollow,noarchive" />
	<title><?=Visualizer::escapeOutput($c->title) ?></title>
</head>
<body class="responsive">
	<?php Visualizer::header(Auth::$caption) ?>
	<?php if ($d): ?>
		<div class="notify warning">
			<?=Visualizer::escapeOutput($d) ?>
		</div>
	<?php endif ?>
	<?php $authAction = $_SERVER["REQUEST_URI"] ?? Visualizer::currentHref($_GET) ?>
	<form class="auth" action="<?=Visualizer::escapeOutput($authAction) ?>" method="post">
		<section>
			<?php if (Auth::$passwordRequired): ?>
				<label for="password"><?=Visualizer::escapeOutput(Auth::$label) ?></label><input type="password" name="password" id="password" /><br />
			<?php endif ?>
			<button type="submit">
				<img src="<?=Visualizer::escapeOutput(Visualizer::actionHref("style", "sendButtonIcon.png")) ?>" alt="" />送信
			</button>
			<input type="hidden" name="token" value="<?=Visualizer::escapeOutput($_SESSION[Auth::SESSION_TOKEN]) ?>">
			<?php Visualizer::delegateParameters($_POST, array("password", "token")) ?>
		</section>
	</form>
	<?php if (Auth::$details !== null): ?>
		<?=Auth::$details ?>
	<?php endif ?>
	<?php Visualizer::footer() ?>
</body>
</html>
