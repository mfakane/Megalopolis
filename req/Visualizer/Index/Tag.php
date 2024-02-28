<?php
namespace Megalopolis;

$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<title>
		<?=Visualizer::escapeOutput(App::$actionName == "author" ? "作者" : "タグ") ?>の一覧 - <?=Visualizer::escapeOutput($c->title) ?>
	</title>
</head>
<body>
	<?php Visualizer::header(App::$actionName == "author" ? "作者の一覧" : "タグの一覧", array
	(
		App::$actionName . "/random" => array("おまかせ表示", "refreshIcon.png")
	)) ?>
	<?php if (App::$actionName == "author" && !$c->showName[Configuration::ON_SUBJECT]): ?>
		<p class="notify warning">
			作者の一覧は公開されていません
		</p>
	<?php elseif (App::$actionName == "tag" && !$c->showTags[Configuration::ON_SUBJECT]): ?>
		<p class="notify warning">
			タグの一覧は公開されていません
		</p>
	<?php else: ?>
		<?php Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHref(App::$actionName) . "/") ?>
		<?php if ($d): ?>
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
				<?php foreach ($byCount as $by => $vals): ?>
					<dt>
						<?=$by ?>
					</dt>
					<dd>
						<ul>
							<?php foreach ($vals as $k => $v): ?>
								<li>
									<?php if (App::$actionName == "author"): ?>
										<?php Visualizer::linkedName($k, '<span class="count">' . $v . '</span>') ?>
									<?php else: ?>
										<?php Visualizer::linkedTag($k, '<span class="count">' . $v . '</span>') ?>
									<?php endif ?>
								</li>
							<?php endforeach ?>
						</ul>
					</dd>
				<?php endforeach ?>
			</dl>
		<?php else: ?>
			<p class="notify info">
				<?=Visualizer::escapeOutput(App::$actionName == "author" ? "作者はいません" : "タグはありません") ?>
			</p>
		<?php endif ?>
		<?php Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHref(App::$actionName) . "/") ?>
	<?php endif ?>
	<?php Visualizer::footer() ?>
</body>
</html>
