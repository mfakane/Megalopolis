<?php
namespace Megalopolis;

require_once __DIR__ . "/../Template.php";

$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;
$isAdmin = Auth::hasSession(true);
Visualizer::doctype();

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
	<?php Visualizer::head() ?>
	<meta name="robots" content="noindex,noarchive" />
	<link rel="search" href="<?=Visualizer::escapeOutput(Visualizer::actionHrefArray(array("search"))) ?>" />
	<?php if ($h->entries): ?>
		<?php if ($h->page > 1): ?>
			<link rel="prev" href="<?=Visualizer::escapeOutput($pagerHref . ($h->page - 1)) ?>" />
		<?php endif ?>
		<?php if ($h->page < $h->pageCount): ?>
			<link rel="next" href="<?=Visualizer::escapeOutput($pagerHref . ($h->page + 1)) ?>" />
		<?php endif ?>
	<?php endif ?>
	<title>
		詳細検索 - <?=Visualizer::escapeOutput($c->title) ?>
	</title>
	<script src="<?=Visualizer::escapeOutput(Visualizer::actionHref("script", "Index", "Index.js")) ?>"></script>
	<script src="<?=Visualizer::escapeOutput(Visualizer::actionHref("script", "Index", "Search.js")) ?>"></script>
</head>
<body class="search">
	<?php Visualizer::header("詳細検索", array(), !is_null($h->entries) ? ($d['count'] <= $c->searchPaging ? "{$d['count']} 件" : "{$d['count']} 件中 " . (($h->page - 1) * $c->searchPaging + 1) . " - " . (($h->page - 1) * $c->searchPaging + count($h->entries)) .  " 件") : null) ?>
	<form>
		<section class="filter">
			<ul class="params">
				<li>
					<label for="query">検索文字列</label><input type="text" name="query" id="query" value="<?=Visualizer::escapeOutput(IndexHandler::param("query")) ?>" />
				</li>
				<li>
					<label for="title">作品名</label><input type="text" name="title" id="title" value="<?=Visualizer::escapeOutput(IndexHandler::param("title")) ?>" />
				</li>
				<?php if ($isAdmin || $c->showName[Configuration::ON_SUBJECT]): ?>
					<li>
						<label for="name">作者</label><input type="text" name="name" id="name" value="<?=Visualizer::escapeOutput(IndexHandler::param("name")) ?>" />
					</li>
				<?php endif ?>
				<?php if ($isAdmin || $c->showTags[Configuration::ON_SUBJECT]): ?>
					<li>
						<label for="tags">タグ</label><input type="text" name="tags" id="tags" value="<?=Visualizer::escapeOutput(IndexHandler::param("tags")) ?>" />
					</li>
				<?php endif ?>
				<?php if ($isAdmin && $c->useAnyPoints() || $c->showRate[Configuration::ON_SUBJECT]): ?>
					<li>
						<label for="eval">評価数</label><input type="number" name="evalBegin" id="eval" value="<?=Visualizer::escapeOutput($d["evalBegin"]) ?>" min="<?=Visualizer::escapeOutput($d["evalMin"]) ?>" max="<?=Visualizer::escapeOutput($d["evalMax"]) ?>" /><span>～</span><input type="number" name="evalEnd" value="<?=Visualizer::escapeOutput($d["evalEnd"]) ?>" min="<?=Visualizer::escapeOutput($d["evalMin"]) ?>" max="<?=Visualizer::escapeOutput($d["evalMax"]) ?>" />
					</li>
				<?php endif ?>
				<?php if ($isAdmin || $c->showPoint[Configuration::ON_SUBJECT]): ?>
					<li>
						<label for="points">POINT</label><input type="number" name="pointsBegin" id="points" value="<?=Visualizer::escapeOutput($d["pointsBegin"]) ?>" min="<?=Visualizer::escapeOutput($d["pointsMin"]) ?>" max="<?=Visualizer::escapeOutput($d["pointsMax"]) ?>" /><span>～</span><input type="number" name="pointsEnd" value="<?=Visualizer::escapeOutput($d["pointsEnd"]) ?>" min="<?=Visualizer::escapeOutput($d["pointsMin"]) ?>" max="<?=Visualizer::escapeOutput($d["pointsMax"]) ?>" />
					</li>
				<?php endif ?>
				<li>
					<label for="dateTime">投稿日時</label><input type="date" name="dateTimeBegin" id="dateTime" value="<?=Visualizer::escapeOutput($d["dateTimeBegin"]) ?>" min="<?=Visualizer::escapeOutput($d["dateTimeMin"]) ?>" max="<?=Visualizer::escapeOutput($d["dateTimeMax"]) ?>" /><span>～</span><input type="date" name="dateTimeEnd" value="<?=Visualizer::escapeOutput($d["dateTimeEnd"]) ?>" min="<?=Visualizer::escapeOutput($d["dateTimeMin"]) ?>" max="<?=Visualizer::escapeOutput($d["dateTimeMax"]) ?>" />
				</li>
			</ul>
			<ul class="buttons">
				<li>
					<button type="submit">
						<img src="<?=Visualizer::escapeOutput(Visualizer::actionHref("style", "searchButtonIcon.png")) ?>" alt="" />検索
					</button>
				</li>
				<li>
					<button type="submit" name="random" value="true">
						<img src="<?=Visualizer::escapeOutput(Visualizer::actionHref("style", "refreshButtonIcon.png")) ?>" alt="" />おまかせ表示
					</button>
				</li>
			</ul>
		</section>
	</form>
	<div id="content">
		<?php if ($h->entries): ?>
			<?php if ($isAdmin): ?>
				<form action="" method="post" id="entriesForm">
			<?php endif ?>
			<?php Visualizer::pager($h->page, $h->pageCount, 5, $pagerHref) ?>
			<?php Template::entries($h->entries, $isAdmin) ?>
			<?php Visualizer::pager($h->page, $h->pageCount, 5, $pagerHref) ?>
			<?php if ($isAdmin): ?>
					<input type="hidden" name="token" value="<?=Visualizer::escapeOutput($_SESSION[Auth::SESSION_TOKEN]) ?>" />
					<section class="admin">
						<ul class="buttons">
							<li>
								<button type="submit" class="unpost" name="admin" value="unpost" id="unpostButton">
									<img src="<?=Visualizer::escapeOutput(Visualizer::actionHref("style", "deleteButtonIcon.png")) ?>" alt="" />選択した作品を削除
								</button>
							</li>
						</ul>
					</section>
				</form>
			<?php endif ?>
		<?php elseif (is_null($h->entries)): ?>
			<p class="notify info">
				検索条件を指定してください
			</p>
		<?php else: ?>
			<p class="notify info">
				条件に合う作品は見つかりませんでした
			</p>
		<?php endif ?>
	</div>
	<?php Visualizer::footer() ?>
</body>
</html>
