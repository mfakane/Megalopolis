<?php
$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<title>
		<?+App::$actionName == "author" ? "作者" : "タグ" ?>の一覧 - <?+$c->title ?>
	</title>
</head>
<body>
	<? Visualizer::header(App::$actionName == "author" ? "作者の一覧" : "タグの一覧", array
	(
		App::$actionName . "/random" => array("おまかせ表示", "refreshIcon.png")
	)) ?>
	<?if (App::$actionName == "author" && !$c->showName[Configuration::ON_SUBJECT]): ?>
		<p class="notify warning">
			作者の一覧は公開されていません
		</p>
	<?elseif (App::$actionName == "tag" && !$c->showTags[Configuration::ON_SUBJECT]): ?>
		<p class="notify warning">
			タグの一覧は公開されていません
		</p>
	<?else: ?>
		<? Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHref(App::$actionName) . "/") ?>
		<?if ($d): ?>
			<?php
			$byCount = array();
			
			foreach ($d as $k => $v)
			{
				$baseCount = strval(substr($v, 0, 1) . str_repeat("0", strlen($v) - 1));
				
				if (!isset($byCount[$baseCount]))
					$byCount[$baseCount] = array();
				
				$byCount[$baseCount][$k] = $v;
			}
			?>
			<dl class="taglist">
				<?foreach ($byCount as $by => $vals): ?>
					<dt>
						<?=$by ?>
					</dt>
					<dd>
						<ul>
							<?foreach ($vals as $k => $v): ?>
								<li>
									<?if (App::$actionName == "author"): ?>
										<? Visualizer::linkedName($k, '<span class="count">' . $v . '</span>') ?>
									<?else: ?>
										<? Visualizer::linkedTag($k, '<span class="count">' . $v . '</span>') ?>
									<?endif ?>
								</li>
							<?endforeach ?>
						</ul>
					</dd>
				<?endforeach ?>
			</dl>
		<?else: ?>
			<p class="notify info">
				<?+App::$actionName == "author" ? "作者はいません" : "タグはありません" ?>
			</p>
		<?endif ?>
		<? Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHref(App::$actionName) . "/") ?>
	<?endif ?>
	<? Visualizer::footer() ?>
</body>
</html>