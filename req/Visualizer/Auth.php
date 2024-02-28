<?php
namespace Megalopolis;

$c = &Configuration::$instance;
$d = &Visualizer::$data;
?>
<?php Visualizer::doctype() ?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<meta name="robots" content="noindex,nofollow,noarchive" />
	<title><?=Visualizer::escapeOutput($c->title) ?></title>
</head>
<body>
	<?php Visualizer::header(Auth::$caption) ?>
	<?php if ($d): ?>
		<div class="notify warning">
			<?=Visualizer::escapeOutput($d) ?>
		</div>
	<?php endif ?>
	<form class="auth" action="<?=Visualizer::escapeOutput(Util::withMobileUniqueIDRequestSuffix(Visualizer::currentHref($_GET))) ?>" method="post">
		<section>
			<label for="password"><?=Visualizer::escapeOutput(Auth::$label) ?></label><input type="password" name="password" id="password" /><br />
			<button type="submit">
				<img src="<?=Visualizer::escapeOutput(Visualizer::actionHref("style", "sendButtonIcon.png")) ?>" alt="" />送信
			</button>
			<input type="hidden" name="token" value="<?=Visualizer::escapeOutput($_SESSION[Auth::SESSION_TOKEN]) ?>">
			<?php Visualizer::delegateParameters($_POST, array("password", "token")) ?>
		</section>
	</form>
	<?php if (Auth::$details): ?>
		<?=Auth::$details ?>
	<?php endif ?>
	<?php Visualizer::footer() ?>
</body>
</html>
