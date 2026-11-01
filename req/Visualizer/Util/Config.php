<?php
namespace Megalopolis;

$c = Configuration::$instance;
$d = Visualizer::$data;
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<?php // スマートフォン向けのスタイルに対応したページから順に viewport を指定する ?>
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,noarchive" />
	<title>設定情報 - <?=Visualizer::escapeOutput($c->title) ?></title>
</head>
<body class="responsive">
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
