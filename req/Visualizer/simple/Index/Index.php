<?php
$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;

if (App::$actionName == "search")
	$title = "検索";
else if (App::$actionName == "tag")
	$title = "タグ: {$d}";
else if (App::$actionName == "author")
	$title = "作者: {$d}";
else
	$title = $h->subject == $h->subjectCount ? "最新作品集" : "作品集 {$h->subject}";

if (App::$actionName == "index")
{
	$h->page = isset($_GET["p"]) ? max(intval(Util::escapeInput($_GET["p"])), 1) : 1;
	$paging = 30;
	$h->pageCount = ceil(count($h->entries) / $paging);
}
else
	$paging = $c->searchPaging;

$offset = ($h->page - 1) * $paging;

if (isset($_GET["s"]))
	switch ($column = Util::escapeInput($_GET["s"]))
	{
		case "title":
		case "name":
			usort($h->entries, create_function('$x, $y', 'return strnatcmp($x->' . $column . ', $y-> ' . $column . ');'));
			
			break;
		case "points":
		case "rate":
		case "size":
		case "dateTime":
			usort($h->entries, create_function('$x, $y', 'return $y->' . $column . ' - $x-> ' . $column . ';'));
			
			break;
	}

Visualizer::doctype();
?>
<html>
<head>
	<? Visualizer::head() ?>
	<title>
		<?+$title ?> - <?+$c->title ?>
	</title>
</head>
<body>
	<h1>
		<?+$title ?>
	</h1>
	<?+$c->title ?>&nbsp;<a href="#menu">メニューへ</a>
	<form class="search" action="<?+Visualizer::actionHref("search") ?>">
		<input type="text" name="query" value="<?+IndexHandler::param("query") ?>" size="18" /><input type="submit" value="検索" />
	</form>
	<? Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHref(array("p" => ""))) ?>
	<?+count($h->entries) ?>件中<?+$offset + 1 ?>～<?+min($offset + $paging, count($h->entries)) ?>件
	<ul class="entries">
		<?foreach (App::$actionName == "index" ? array_slice($h->entries, $offset, $paging) : $h->entries as $i): ?>
			<li>
				<?if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
					<a href="<?+Visualizer::actionHref($i->subject, $i->id) ?>"><?+$i->title ?></a><br />
				<?endif ?>
				<span class="dateTime"><?+Visualizer::formatShortDateTime($i->dateTime) ?></span>
				<?if ($c->showName[Configuration::ON_SUBJECT]): ?>
					<span class="name"><? Visualizer::convertedName($i->name) ?></span>
				<?endif ?>
				<?if ($c->useAnyPoints()): ?>
					<br />
					<?if ($c->showPoint[Configuration::ON_SUBJECT]): ?>
						<span class="points">Pt:<?+$i->points ?></span>
					<?endif ?>
					<?if ($c->showRate[Configuration::ON_SUBJECT]): ?>
						<span class="rate">Rt:<?+$i->rate ?></span>
					<?endif ?>
				<?endif ?>
				<?if ($c->showSize[Configuration::ON_SUBJECT]): ?>
					<span class="size">Sz:<?+$i->size ?>KB</span>
				<?endif ?>
				<?if ($c->showTags[Configuration::ON_SUBJECT]): ?>
					<br />
					<span class="tags"><?+implode(" ", $i->tags) ?></span>
				<?endif ?>
			</li>
		<?endforeach ?>
	</ul>
	<? Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHref(array("p" => ""))) ?>
	<ul id="menu" class="menu">
		<? $i = 0 ?>
		<?foreach (array
		(
			"#" => "上へ戻る",
			"random" => "おまかせ表示",
			"s=title" => ($c->showTitle[Configuration::ON_SUBJECT] ? "作品名順" : null),
			"s=name" => ($c->showName[Configuration::ON_SUBJECT] ? "作者順" : null),
			"s=points" => ($c->useAnyPoints() && $c->showPoint[Configuration::ON_SUBJECT] ? "POINT順" : null),
			"s=rate" => ($c->useAnyPoints() && $c->showPoint[Configuration::ON_SUBJECT] ? "Rate順" : null),
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
				[<?=++$i ?>]<a href="<?+$k == "#" ? $k : Visualizer::actionHref($isParam ? (App::$actionName == "index" ? $h->subject : App::$actionName) : $k, $isParam ? (App::$actionName == "search" ? array("query" => IndexHandler::param("query"), $param[0] => $param[1]) : array($param[0] => $param[1])) : null) ?>" accesskey="<?=$i ?>"><?+$v ?></a>
			</li>
		<?endforeach ?>
		<?if (App::$actionName == "index"): ?>
			<li>
				[9]<a href="<?+Visualizer::actionHref(array("visualizer" => "normal")) ?>" accesskey="9">PC版表示</a>
			</li>
			<li>
				<form action="<?+Visualizer::actionHref() ?>">
					<div>
						<label accesskey="0">
							[0]作品集:
							<select name="log">
								<?foreach (range($h->subjectCount, 1, -1) as $i): ?>
									<option value="<?+$i ?>"<?=$i == $h->subject ? ' selected=""' : null ?>><?+$i ?></option>
								<?endforeach ?>
							</select>
						</label>
						<input type="submit" value="移動" />
					</div>
				</form>
			</li>
		<?else: ?>
			<li>
				[0]<a href="<?+Visualizer::actionHref() ?>" accesskey="0">トップページへ戻る</a>
			</li>
		<?endif ?>
	</ul>
</body>
</html>