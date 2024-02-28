<?php
namespace Megalopolis;

$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;
$searchMode = "query";
$search = "";

if (!isset($h->entries)) $h->entries = [];

if (App::$actionName == "search")
{
	$title = "検索";
	
	if (!is_null($search = IndexHandler::param("query")))
	{
		$searchMode = "query";
		$title = "検索";
	}
	else if (!is_null($search = IndexHandler::param("title")))
	{
		$searchMode = "title";
		$title = "作品名検索";
	}
	else if (!is_null($search = IndexHandler::param("name")))
	{
		$searchMode = "name";
		$title = "作者検索";
	}
	else if (!is_null($search = IndexHandler::param("tag")))
	{
		$searchMode = "tag";
		$title = "タグ検索";
	}
}
else if (App::$actionName == "tag")
	$title = "タグ: {$d}";
else if (App::$actionName == "author")
	$title = "作者: {$d}";
else
	$title = $h->subject == $h->subjectCount ? "最新作品集" : "作品集 {$h->subject}";

if (App::$actionName == "index")
{
	$h->page = isset($_GET["p"]) ? max(intval(IndexHandler::param("p")), 1) : 1;
	$paging = 50;
	$h->pageCount = (int)ceil((float)count($h->entries) / $paging);
}
else
	$paging = $c->searchPaging;

$offset = ($h->page - 1) * $paging;

if (isset($_GET["s"]))
	switch ($column = IndexHandler::param("s"))
	{
		case "title":
			usort($h->entries, fn($x, $y) => strnatcmp($x->title, $y->title));
			
			break;
		case "name":
			usort($h->entries, fn($x, $y) => strnatcmp($x->name, $y->name));
			
			break;
		case "commentCount":
			usort($h->entries, fn($x, $y): int => $y->commentCount - $x->commentCount);
			
			break;
		case "points":
			usort($h->entries, fn($x, $y): int => $y->points - $x->points);
			
			break;
		case "rate":
			usort($h->entries, fn($x, $y): int => $y->rate - $x->rate);
			
			break;
		case "size":
			usort($h->entries, fn($x, $y): int => $y->size - $x->size);
			
			break;
		case "dateTime":
			usort($h->entries, fn($x, $y): int => $y->dateTime - $x->dateTime);
			
			break;
	}

Visualizer::doctype();
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<title>
		<?=Visualizer::escapeOutput($title) ?> - <?=Visualizer::escapeOutput($c->title) ?>
	</title>
</head>
<body>
	<h1>
		<?=Visualizer::escapeOutput($title) ?>
	</h1>
	<?=Visualizer::escapeOutput($c->title) ?>&nbsp;<a href="#menu">メニューへ</a>
	<?php if ($c->showTitle[Configuration::ON_SUBJECT] && $c->useSearch): ?>
		<form class="search" action="<?=Visualizer::escapeOutput(Visualizer::actionHref("search")) ?>">
			<input type="text" name="query" value="<?=Visualizer::escapeOutput($search) ?>" size="18" /><input type="submit" value="検索" /><br />
			<label><input type="radio" name="mode" value="query"<?=$searchMode == "query" ? ' checked="checked"' : null ?> />全文</label>
			<label><input type="radio" name="mode" value="title"<?=$searchMode == "title" ? ' checked="checked"' : null ?> />作品名</label>
			<?php if ($c->showName[Configuration::ON_SUBJECT]): ?>
				<label><input type="radio" name="mode" value="name"<?=$searchMode == "name" ? ' checked="checked"' : null ?> />作者</label>
			<?php endif ?>
			<?php if ($c->showTags[Configuration::ON_SUBJECT]): ?>
				<label><input type="radio" name="mode" value="tag"<?=$searchMode == "tag" ? ' checked="checked"' : null ?> />分類タグ</label>
			<?php endif ?>
		</form>
	<?php elseif (Configuration::$instance->customSearch): ?>
		<form class="search" action="<?=Visualizer::escapeOutput(Visualizer::actionHref("search")) ?>">
			<input type="text" name="<?=Visualizer::escapeOutput(Configuration::$instance->customSearch[1]) ?>" value="<?=Visualizer::escapeOutput($search) ?>" size="18" /><input type="submit" value="検索" />
			<?php
			if (isset(Configuration::$instance->customSearch[2]))
				foreach (Configuration::$instance->customSearch[2] as $k => $v)
					echo '<input type="hidden" name="', Visualizer::escapeOutput($k), '" value="', Visualizer::escapeOutput($v), '" />';
			?>
		</form>
	<?php else: ?>
		<br />
	<?php endif ?>
	<?php Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHref((App::$actionName == "index" ? $h->subject : App::$actionName), (App::$actionName == "search" ? array("query" => $search, "mode" => $searchMode) : array()) + array("p" => ""))) ?>
	<?=Visualizer::escapeOutput(App::$actionName == "search" ? $d["count"] : (App::$actionName == "index" ? count($h->entries) : $h->entryCount)) ?>件中<?=Visualizer::escapeOutput($offset + 1) ?>～<?=Visualizer::escapeOutput($offset + min($paging, count($h->entries))) ?>件
	<ul class="entries">
		<?php if ($h->entries): ?>
			<?php foreach (App::$actionName == "index" ? array_slice($h->entries, $offset, $paging) : $h->entries as $i): ?>
				<li>
					<?php if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
						<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($i->subject, $i->id)) ?>"><?=Visualizer::escapeOutput($i->title) ?></a><br />
					<?php endif ?>
					<span class="dateTime"><?=Visualizer::escapeOutput(Visualizer::formatShortDateTime($i->dateTime)) ?></span>
					<?php if ($c->showName[Configuration::ON_SUBJECT]): ?>
						<span class="name"><?php Visualizer::convertedName($i->name) ?></span>
					<?php endif ?>
					<?php if ($c->showComment[Configuration::ON_SUBJECT] || $c->showPoint[Configuration::ON_SUBJECT] || $c->showRate[Configuration::ON_SUBJECT]): ?>
						<br />
						<?php if ($c->showComment[Configuration::ON_SUBJECT]): ?>
							<span class="commentCount">Cm:<?=Visualizer::escapeOutput($i->commentCount) ?></span>
						<?php endif ?>
						<?php if ($c->showPoint[Configuration::ON_SUBJECT]): ?>
							<span class="points">Pt:<?=Visualizer::escapeOutput($i->points) ?></span>
						<?php endif ?>
						<?php if ($c->showRate[Configuration::ON_SUBJECT]): ?>
							<span class="rate">Rt:<?=Visualizer::escapeOutput($i->rate) ?></span>
						<?php endif ?>
					<?php endif ?>
					<?php if ($c->showSize[Configuration::ON_SUBJECT]): ?>
						<span class="size">Sz:<?=Visualizer::escapeOutput($i->size) ?>KB</span>
					<?php endif ?>
					<?php if ($c->showTags[Configuration::ON_SUBJECT]): ?>
						<br />
						<span class="tags"><?=Visualizer::escapeOutput(implode(" ", $i->tags)) ?></span>
					<?php endif ?>
				</li>
			<?php endforeach ?>
		<?php else: ?>
			<li>結果はありません</li>
		<?php endif ?>
	</ul>
	<?php Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHref((App::$actionName == "index" ? $h->subject : App::$actionName), (App::$actionName == "search" ? array("query" => $search, "mode" => $searchMode) : array()) + array("p" => ""))) ?>
	<ul class="menu">
		<a id="menu" name="menu">メニュー</a>
		<?php $i = 0 ?>
		<?php foreach (array
		(
			"#" => "上へ戻る",
			"random" => "おまかせ表示",
			"s=title" => ($c->showTitle[Configuration::ON_SUBJECT] ? "作品名順" : null),
			"s=name" => ($c->showName[Configuration::ON_SUBJECT] ? "作者順" : null),
			"s=commentCount" => ($c->showComment[Configuration::ON_SUBJECT] ? "コメント数順" : null),
			"s=points" => ($c->showPoint[Configuration::ON_SUBJECT] ? "POINT順" : null),
			"s=rate" => ($c->showPoint[Configuration::ON_SUBJECT] ? "Rate順" : null),
			"s=size" => ($c->showSize[Configuration::ON_SUBJECT] ? "サイズ順" : null),
			"s=dateTime" => "日時順"
		) as $k => $v): ?>
			<li>
				<?php
				if (!$v)
					continue;
				
				$isParam = strstr($k, "=");
				$param = $isParam ? explode("=", $k) : array();
				?>
				[<?=++$i ?>]<a href="<?=Visualizer::escapeOutput($k == "#" ? $k : Visualizer::actionHref($isParam ? (App::$actionName == "index" ? $h->subject : App::$actionName) : $k, $isParam ? (App::$actionName == "search" ? array("query" => $search, "mode" => $searchMode, $param[0] => $param[1]) : array($param[0] => $param[1])) : null)) ?>" accesskey="<?=$i ?>"><?=Visualizer::escapeOutput($v) ?></a>
			</li>
		<?php endforeach ?>
		<?php if (App::$actionName == "index"): ?>
			<li>
				[9]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref(array("visualizer" => "normal"))) ?>" accesskey="9">PC版表示</a>
			</li>
			<li>
				<form action="<?=Visualizer::escapeOutput(Visualizer::actionHref()) ?>">
					<div>
						<label accesskey="0">
							[0]作品集:
							<select name="log">
								<?php foreach (range($h->subjectCount, 1, -1) as $i): ?>
									<option value="<?=Visualizer::escapeOutput($i) ?>"<?=$i == $h->subject ? ' selected=""' : null ?>><?=Visualizer::escapeOutput($i) ?></option>
								<?php endforeach ?>
							</select>
						</label>
						<input type="submit" value="移動" />
					</div>
				</form>
			</li>
		<?php else: ?>
			<li>
				[0]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref()) ?>" accesskey="0">トップページへ戻る</a>
			</li>
		<?php endif ?>
	</ul>
</body>
</html>
