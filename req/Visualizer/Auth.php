<?php
$c = &Configuration::$instance;
$d = &Visualizer::$data;
?>
<? Visualizer::doctype() ?>
<html>
<head>
	<? Visualizer::head() ?>
	<title><?+$c->title ?></title>
</head>
<body>
	<? Visualizer::header(Auth::$caption) ?>
	<?if ($d): ?>
		<div class="notify warning">
			<?+$d ?>
		</div>
	<?endif ?>
	<form class="auth" action="<?+Util::withMobileUniqueIDRequestSuffix() ?>" method="post">
		<section>
			<label for="password"><?+Auth::$label ?></label><input type="password" name="password" id="password" /><br />
			<button type="submit">
				<img src="<?+Visualizer::actionHref("style", "sendButtonIcon.png") ?>" />送信
			</button>
			<input type="hidden" name="token" value="<?+$_SESSION[Auth::SESSION_TOKEN] ?>">
		</section>
	</form>
	<?if (Auth::$details): ?>
		<?=Auth::$details ?>
	<?endif ?>
	<? Visualizer::footer() ?>
</body>
</html>