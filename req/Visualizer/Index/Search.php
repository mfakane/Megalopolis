<?php
$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;
$isAdmin = Auth::hasSession(true);
Visualizer::doctype();
App::load(VISUALIZER_DIR . "Template/Index");

$pagerHref = Visualizer::actionHref("search", array
(
	"query" => IndexHandler::param("query"),
	"title" => IndexHandler::param("title"),
	"name" => IndexHandler::param("name"),
	"tags" => IndexHandler::param("tags"),
	"evalBegin" => IndexHandler::param("evalBegin"),
	"evalEnd" => IndexHandler::param("evalEnd"),
	"pointsBegin" => IndexHandler::param("pointsBegin"),
	"pointsEnd" => IndexHandler::param("pointsEnd"),
	"dateTimeBegin" => IndexHandler::param("dateTimeBegin"),
	"dateTimeEnd" => IndexHandler::param("dateTimeEnd"),
	"p" => ""
));
?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<meta name="robots" content="noindex,noarchive" />
	<link rel="search" href="<?+Visualizer::actionHrefArray(array("search")) ?>" />
	<?if ($h->entries): ?>
		<?if ($h->page > 1): ?>
			<link rel="prev" href="<?+$pagerHref . ($h->page - 1) ?>" />
		<?endif ?>
		<?if ($h->page < $h->pageCount): ?>
			<link rel="next" href="<?+$pagerHref . ($h->page + 1) ?>" />
		<?endif ?>
	<?endif ?>
	<title>
		詳細検索 - <?+$c->title ?>
	</title>
	<script src="<?+Visualizer::actionHref("script", "Index", "Index.js") ?>"></script>
	<script src="<?+Visualizer::actionHref("script", "Index", "Search.js") ?>"></script>
</head>
<body class="search">
	<? Visualizer::header("詳細検索", array(), !is_null($h->entries) ? ($d['count'] <= $c->searchPaging ? "{$d['count']} 件" : "{$d['count']} 件中 " . (($h->page - 1) * $c->searchPaging + 1) . " - " . (($h->page - 1) * $c->searchPaging + count($h->entries)) .  " 件") : null) ?>
	<form>
		<section class="filter">
			<ul class="params">
				<li>
					<label for="query">検索文字列</label><input type="text" name="query" id="query" value="<?+IndexHandler::param("query") ?>" />
				</li>
				<li>
					<label for="title">作品名</label><input type="text" name="title" id="title" value="<?+IndexHandler::param("title") ?>" />
				</li>
				<?if ($isAdmin || $c->showName[Configuration::ON_SUBJECT]): ?>
					<li>
						<label for="name">作者</label><input type="text" name="name" id="name" value="<?+IndexHandler::param("name") ?>" />
					</li>
				<?endif ?>
				<?if ($isAdmin || $c->showTags[Configuration::ON_SUBJECT]): ?>
					<li>
						<label for="tags">タグ</label><input type="text" name="tags" id="tags" value="<?+IndexHandler::param("tags") ?>" />
					</li>
				<?endif ?>
				<?if ($isAdmin && $c->useAnyPoints() || $c->showRate[Configuration::ON_SUBJECT]): ?>
					<li>
						<label for="eval">評価数</label><input type="number" name="evalBegin" id="eval" value="<?+$d["evalBegin"] ?>" min="<?+$d["evalMin"] ?>" max="<?+$d["evalMax"] ?>" /><span>～</span><input type="number" name="evalEnd" value="<?+$d["evalEnd"] ?>" min="<?+$d["evalMin"] ?>" max="<?+$d["evalMax"] ?>" />
					</li>
				<?endif ?>
				<?if ($isAdmin || $c->showPoint[Configuration::ON_SUBJECT]): ?>
					<li>
						<label for="points">POINT</label><input type="number" name="pointsBegin" id="points" value="<?+$d["pointsBegin"] ?>" min="<?+$d["pointsMin"] ?>" max="<?+$d["pointsMax"] ?>" /><span>～</span><input type="number" name="pointsEnd" value="<?+$d["pointsEnd"] ?>" min="<?+$d["pointsMin"] ?>" max="<?+$d["pointsMax"] ?>" />
					</li>
				<?endif ?>
				<li>
					<label for="dateTime">投稿日時</label><input type="date" name="dateTimeBegin" id="dateTime" value="<?+$d["dateTimeBegin"] ?>" min="<?+$d["dateTimeMin"] ?>" max="<?+$d["dateTimeMax"] ?>" /><span>～</span><input type="date" name="dateTimeEnd" value="<?+$d["dateTimeEnd"] ?>" min="<?+$d["dateTimeMin"] ?>" max="<?+$d["dateTimeMax"] ?>" />
				</li>
			</ul>
			<ul class="buttons">
				<li>
					<button type="submit">
						<img src="<?+Visualizer::actionHref("style", "searchButtonIcon.png") ?>" alt="" />検索
					</button>
				</li>
				<li>
					<button type="submit" name="random" value="true">
						<img src="<?+Visualizer::actionHref("style", "refreshButtonIcon.png") ?>" alt="" />おまかせ表示
					</button>
				</li>
			</ul>
		</section>
	</form>
	<div id="content">
		<?if ($h->entries): ?>
			<?if ($isAdmin): ?>
				<form action="" method="post" id="entriesForm">
			<?endif ?>
			<? Visualizer::pager($h->page, $h->pageCount, 5, $pagerHref) ?>
			<? entries($h->entries, $isAdmin) ?>
			<? Visualizer::pager($h->page, $h->pageCount, 5, $pagerHref) ?>
			<?if ($isAdmin): ?>
					<input type="hidden" name="token" value="<?+$_SESSION[Auth::SESSION_TOKEN] ?>" />
					<section class="admin">
						<ul class="buttons">
							<li>
								<button type="submit" class="unpost" name="admin" value="unpost" id="unpostButton">
									<img src="<?+Visualizer::actionHref("style", "deleteButtonIcon.png") ?>" alt="" />選択した作品を削除
								</button>
							</li>
						</ul>
					</section>
				</form>
			<?endif ?>
		<?elseif (is_null($h->entries)): ?>
			<p class="notify info">
				検索条件を指定してください
			</p>
		<?else: ?>
			<p class="notify info">
				条件に合う作品は見つかりませんでした
			</p>
		<?endif ?>
	</div>
	<? Visualizer::footer() ?>
</body>
</html>