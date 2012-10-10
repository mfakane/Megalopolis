<?php
$c = &Configuration::$instance;
$d = &Visualizer::$data;
?>
<? Visualizer::doctype() ?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<meta name="robots" content="noindex,nofollow,noarchive" />
	<title><?+$c->title ?></title>
</head>
<body>
	<? Visualizer::header(Auth::$caption) ?>
	<?if ($d): ?>
		<div class="notify warning">
			<?+$d ?>
		</div>
	<?endif ?>
	<form class="auth" action="<?+Util::withMobileUniqueIDRequestSuffix(Visualizer::currentHref($_GET)) ?>" method="post">
		<section>
			<label for="password"><?+Auth::$label ?></label><input type="password" name="password" id="password" /><br />
			<button type="submit">
				<img src="<?+Visualizer::actionHref("style", "sendButtonIcon.png") ?>" alt="" />送信
			</button>
			<input type="hidden" name="token" value="<?+$_SESSION[Auth::SESSION_TOKEN] ?>">
			<? Visualizer::delegateParameters($_POST, array("password", "token")) ?>
		</section>
	</form>
	<?if (Auth::$details): ?>
		<?=Auth::$details ?>
	<?endif ?>
	<? Visualizer::footer() ?>
</body>
</html>