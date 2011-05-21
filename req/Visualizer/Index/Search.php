<?php
$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;
$isAdmin = Auth::hasSession(true);
Visualizer::doctype();
?>
<html>
<head>
	<? Visualizer::head() ?>
	<title>
		詳細検索 - <?+$c->title ?>
	</title>
	<script src="<?+Visualizer::actionHref("script", "Index", "Index.js") ?>"></script>
</head>
<body class="search">
	<? Visualizer::header("詳細検索", array
	(
		", ホームへ戻る, returnIcon.png",
	), !is_null($h->entries) ? ($d['count'] <= $c->searchPaging ? "{$d['count']} 件" : "{$d['count']} 件中 " . (($h->page - 1) * $c->searchPaging + 1) . " - " . (($h->page - 1) * $c->searchPaging + count($h->entries)) .  " 件") : null) ?>
	<form>
		<section>
			<label for="query">検索文字列</label><input type="text" name="query" id="query" value="<?+IndexHandler::param("query") ?>" />
			<label for="title">作品名</label><input type="text" name="title" id="title" value="<?+IndexHandler::param("title") ?>" />
			<label for="name">作者</label><input type="text" name="name" id="name" value="<?+IndexHandler::param("name") ?>" />
			<label for="tags">タグ</label><input type="text" name="tags" id="tags" value="<?+IndexHandler::param("tags") ?>" />
			<label for="eval">評価数</label><input type="number" name="evalBegin" id="eval" value="<?+$d["evalBegin"] ?>" min="<?+$d["evalMin"] ?>" max="<?+$d["evalMax"] ?>" /><span>～</span><input type="number" name="evalEnd" value="<?+$d["evalEnd"] ?>" min="<?+$d["evalMin"] ?>" max="<?+$d["evalMax"] ?>" />
			<label for="points">POINT</label><input type="number" name="pointsBegin" id="points" value="<?+$d["pointsBegin"] ?>" min="<?+$d["pointsMin"] ?>" max="<?+$d["pointsMax"] ?>" /><span>～</span><input type="number" name="pointsEnd" value="<?+$d["pointsEnd"] ?>" min="<?+$d["pointsMin"] ?>" max="<?+$d["pointsMax"] ?>" />
			<label for="dateTime">投稿日時</label><input type="date" name="dateTimeBegin" id="dateTime" value="<?+$d["dateTimeBegin"] ?>" min="<?+$d["dateTimeMin"] ?>" max="<?+$d["dateTimeMax"] ?>" /><span>～</span><input type="date" name="dateTimeEnd" value="<?+$d["dateTimeEnd"] ?>" min="<?+$d["dateTimeMin"] ?>" max="<?+$d["dateTimeMax"] ?>" />
			<ul class="buttons">
				<li>
					<button type="submit">
						<img src="<?+Visualizer::actionHref("style", "searchButtonIcon.png") ?>" />検索
					</button>
				</li>
				<li>
					<button type="submit" name="random" value="true">
						<img src="<?+Visualizer::actionHref("style", "refreshButtonIcon.png") ?>" />おまかせ表示
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
			<div class="entries">
				<?foreach ($h->entries as $idx => $i): ?><article>
					<?if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
						<h2>
							<?if ($isAdmin): ?>
								<label>
									<input type="checkbox" name="id[]" value="<?+$i->id ?>" />
							<?endif ?>
							<a href="<?+Visualizer::actionHref($i->subject, $i->id) ?>"><?+$i->title ?></a>
							<?if ($isAdmin): ?>
								</label>
							<?endif ?>
						</h2>
					<?endif ?>
					<time pubdate="pubdate" datetime="<?+date("c", $i->lastUpdate) ?>">
						<?+Visualizer::formatDateTime($i->lastUpdate) ?>
					</time>
					<?if ($c->showName[Configuration::ON_SUBJECT]): ?>
						<a href="<?+Visualizer::actionHref("author", $i->name) ?>"><? Visualizer::convertedName($i->name) ?></a>
					<?endif ?>
					<?if ($c->showPages[Configuration::ON_SUBJECT] ||
						  $c->showSize[Configuration::ON_SUBJECT] ||
						  $c->showReadCount[Configuration::ON_SUBJECT] ||
						  $c->useAnyPoints() && ($c->showPoint[Configuration::ON_SUBJECT] || $c->showRate[Configuration::ON_SUBJECT])): ?>
					<dl>
						<?if ($c->showPages[Configuration::ON_SUBJECT]): ?>
							<dt>ページ数</dt>
							<dd>
								<?+$i->pageCount ?>
							</dd>
						<?endif ?>
						<?if ($c->showSize[Configuration::ON_SUBJECT]): ?>
							<dt>サイズ</dt>
							<dd>
								<?+$i->size ?>KB
							</dd>
						<?endif ?>
						<?if ($c->showReadCount[Configuration::ON_SUBJECT]): ?>
							<dt>閲覧数</dt>
							<dd>
								<?+$i->readCount ?>
							</dd>
						<?endif ?>
						<?if ($c->useAnyPoints()): ?>
							<?if ($c->showPoint[Configuration::ON_SUBJECT]): ?>
								<dt>評価数</dt>
								<dd>
									<?+$i->evaluationCount ?>
								</dd>
								<dt>POINT</dt>
								<dd>
									<?+$i->points ?>
								</dd>
							<?endif ?>
							<?if ($c->showRate[Configuration::ON_SUBJECT]): ?>
								<dt>Rate</dt>
								<dd>
									<?+sprintf("%.2f", $i->rate) ?>
								</dd>
							<?endif ?>
						<?endif ?>
					</dl>
					<?endif ?>
					<?if ($i->tags && $c->showTags[Configuration::ON_SUBJECT]): ?>
						<ul class="tags">
							<?foreach ($i->tags as $j): ?>
								<li>
									<a href="<?+Visualizer::actionHref("tag", $j) ?>"><?+$j ?></a>
								</li>
							<?endforeach ?>
						</ul>
					<?endif ?>
					<?if (!Util::isEmpty($i->summary) && $c->showSummary[Configuration::ON_SUBJECT]): ?>
						<p>
							<? Visualizer::convertedSummary($i->summary) ?>
						</p>
					<?endif ?>
				</article><?endforeach ?>
			</div>
			<? Visualizer::pager($h->page, $h->pageCount, 5, Visualizer::actionHref("search", array
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
			))) ?>
			<?if ($isAdmin): ?>
					<input type="hidden" name="sessionID" value="<?+Auth::getSessionID() ?>" />
					<section class="admin">
						<ul class="buttons">
							<li>
								<button type="submit" class="unpost" name="admin" value="unpost" id="unpostButton">
									<img src="<?+Visualizer::actionHref("style", "deleteButtonIcon.png") ?>" />選択した作品を削除
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