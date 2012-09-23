<?php
$h = UtilHandler::$instance;
$c = &Configuration::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<meta name="robots" content="noindex,noarchive" />
	<title>設定情報 - <?+$c->title ?></title>
</head>
<body>
	<? Visualizer::header("設定情報") ?>
	<?foreach ($d as $name => $data): ?>
		<section>
			<h2>
				<?+$name ?>
			</h2>
			<dl class="config">
			<?foreach ($data as $k => $v): ?>
				<?php
					list($title, $key, $value) = is_array($v)
						? array($k, $v[0], $v[1])
						: array($k, $k, $v);
					
					if (is_bool($value))
						$value = $value ? "はい" : "いいえ";
					else if (is_array($value))
						$value = implode(", ", $value);
				?>
				<dt title="<?+$title ?>">
					<?+$key ?>
				</dt>
				<dd>
					<?+$value ?>
				</dd>
			<?endforeach ?>
			</dl>
		</section>
	<?endforeach ?>
	<? Visualizer::footer() ?>
</body>
</html>