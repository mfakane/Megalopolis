<?php
namespace Megalopolis;

$h = UtilHandler::$instance;
$c = &Configuration::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<meta name="robots" content="noindex,noarchive" />
	<title>設定情報 - <?=Visualizer::escapeOutput($c->title) ?></title>
</head>
<body>
	<?php Visualizer::header("設定情報") ?>
	<?php foreach ($d as $name => $data): ?>
		<section>
			<h2>
				<?=Visualizer::escapeOutput($name) ?>
			</h2>
			<dl class="config">
			<?php foreach ($data as $k => $v): ?>
				<?php
					list($title, $key, $value) = is_array($v)
						? array($k, $v[0], $v[1])
						: array($k, $k, $v);
					
					if (is_bool($value))
						$value = $value ? "はい" : "いいえ";
					else if (is_array($value))
						$value = implode(", ", $value);
				?>
				<dt title="<?=Visualizer::escapeOutput($title) ?>">
					<?=Visualizer::escapeOutput($key) ?>
				</dt>
				<dd>
					<?=Visualizer::escapeOutput($value) ?>
				</dd>
			<?php endforeach ?>
			</dl>
		</section>
	<?php endforeach ?>
	<?php Visualizer::footer() ?>
</body>
</html>
