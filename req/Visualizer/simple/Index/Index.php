<?php
$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;
$searchMode = "query";
$search = "";

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
	$h->page = isset($_GET["p"]) ? max(intval(Util::escapeInput($_GET["p"])), 1) : 1;
	$paging = 50;
	$h->pageCount = ceil(count($h->entries) / $paging);
}
else
	$paging = $c->searchPaging;

$offset = ($h->page - 1) * $paging;

if (isset($_GET["s"]))
	switch ($column = Util::escapeInput($_GET["s"]))
	{
		case "title":
			usort($h->entries, create_function('$x, $y', 'return strnatcmp($x->title, $y->title);'));
			
			break;
		case "name":
			usort($h->entries, create_function('$x, $y', 'return strnatcmp($x->name, $y->name);'));
			
			break;
		case "commentCount":
			usort($h->entries, create_function('$x, $y', 'return $y->commentCount - $x->commentCount;'));
			
			break;
		case "points":
			usort($h->entries, create_function('$x, $y', 'return $y->points - $x->points;'));
			
			break;
		case "rate":
			usort($h->entries, create_function('$x, $y', 'return $y->rate - $x->rate;'));
			
			break;
		case "size":
			usort($h->entries, create_function('$x, $y', 'return $y->size - $x->size;'));
			
			break;
		case "dateTime":
			usort($h->entries, create_function('$x, $y', 'return $y->dateTime - $x->dateTime;'));
			
			break;
	}

Visualizer::doctype();
?>
<html lang="ja">
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
	<?if ($c->showTitle[Configuration::ON_SUBJECT] && $c->useSearch): ?>
		<form class="search" action="<?+Visualizer::actionHref("search") ?>">
			<input type="text" name="query" value="<?+$search ?>" size="18" /><input type="submit" value="検索" /><br />
			<label><input type="radio" name="mode" value="query"<?=$searchMode == "query" ? ' checked="checked"' : null ?> />全文</label>
			<label><input type="radio" name="mode" value="title"<?=$searchMode == "title" ? ' checked="checked"' : null ?> />作品名</label>
			<?if ($c->showName[Configuration::ON_SUBJECT]): ?>
				<label><input type="radio" name="mode" value="name"<?=$searchMode == "name" ? ' checked="checked"' : null ?> />作者</label>
			<?endif ?>
			<?if ($c->showTags[Configuration::ON_SUBJECT]): ?>
				<label><input type="radio" name="mode" value="tag"<?=$searchMode == "tag" ? ' checked="checked"' : null ?> />分類タグ</label>
			<?endif ?>
		</form>
	<?elseif (Configuration::$instance->customSearch): ?>
		<form class="search" action="<?+Visualizer::actionHref("search") ?>">
			<input type="text" name="<?+Configuration::$instance->customSearch[1] ?>" value="<?+$search ?>" size="18" /><input type="submit" value="検索" />
			<?php
			if (isset(Configuration::$instance->customSearch[2]))
				foreach (Configuration::$instance->customSearch[2] as $k => $v)
					echo '<input type="hidden" name="', Visualizer::converted($k), '" value="', Visualizer::converted($v), '" />';
			?>
		</form>
	<?else: ?>
		<br />
	<?endif ?>
	<? Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHref((App::$actionName == "index" ? $h->subject : App::$actionName), (App::$actionName == "search" ? array("query" => $search, "mode" => $searchMode) : array()) + array("p" => ""))) ?>
	<?+App::$actionName == "search" ? $d["count"] : (App::$actionName == "index" ? count($h->entries) : $h->entryCount) ?>件中<?+$offset + 1 ?>～<?+$offset + min($paging, count($h->entries)) ?>件
	<ul class="entries">
		<?if ($h->entries): ?>
			<?foreach (App::$actionName == "index" ? array_slice($h->entries, $offset, $paging) : $h->entries as $i): ?>
				<li>
					<?if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
						<a href="<?+Visualizer::actionHref($i->subject, $i->id) ?>"><?+$i->title ?></a><br />
					<?endif ?>
					<span class="dateTime"><?+Visualizer::formatShortDateTime($i->dateTime) ?></span>
					<?if ($c->showName[Configuration::ON_SUBJECT]): ?>
						<span class="name"><? Visualizer::convertedName($i->name) ?></span>
					<?endif ?>
					<?if ($c->showComment[Configuration::ON_SUBJECT] || $c->showPoint[Configuration::ON_SUBJECT] || $c->showRate[Configuration::ON_SUBJECT]): ?>
						<br />
						<?if ($c->showComment[Configuration::ON_SUBJECT]): ?>
							<span class="commentCount">Cm:<?+$i->commentCount ?></span>
						<?endif ?>
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
		<?else: ?>
			<li>結果はありません</li>
		<?endif ?>
	</ul>
	<? Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHref((App::$actionName == "index" ? $h->subject : App::$actionName), (App::$actionName == "search" ? array("query" => $search, "mode" => $searchMode) : array()) + array("p" => ""))) ?>
	<ul class="menu">
		<a id="menu" name="menu">メニュー</a>
		<? $i = 0 ?>
		<?foreach (array
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
				[<?=++$i ?>]<a href="<?+$k == "#" ? $k : Visualizer::actionHref($isParam ? (App::$actionName == "index" ? $h->subject : App::$actionName) : $k, $isParam ? (App::$actionName == "search" ? array("query" => $search, "mode" => $searchMode, $param[0] => $param[1]) : array($param[0] => $param[1])) : null) ?>" accesskey="<?=$i ?>"><?+$v ?></a>
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