<?php
namespace Megalopolis;

require_once __DIR__ . "/../Template.php";

$h = UtilHandler::$instance;
$c = &Configuration::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();

$pagerHref = Visualizer::actionHref("util", "track", array
(
	"host" => $d["host"],
	"subjectBegin" => $d["subjectBegin"],
	"subjectEnd" => $d["subjectEnd"],
	"target" => implode(",", $d["target"]),
	"p" => ""
));
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<link rel="search" href="<?=Visualizer::escapeOutput(Visualizer::actionHrefArray(array("search"))) ?>" />
	<?php if ($d["entries"]): ?>
		<?php if ($d["page"] > 1): ?>
			<link rel="prev" href="<?=Visualizer::escapeOutput($pagerHref . ($d["page"] - 1)) ?>" />
		<?php endif ?>
		<?php if ($d["page"] < $d["pageCount"]): ?>
			<link rel="next" href="<?=Visualizer::escapeOutput($pagerHref . ($d["page"] + 1)) ?>" />
		<?php endif ?>
	<?php endif ?>
	<title>ホスト検索 - <?=Visualizer::escapeOutput($c->title) ?></title>
	<script src="<?=Visualizer::escapeOutput(Visualizer::actionHref("script", "Index", "Index.js")) ?>"></script>
	<script src="<?=Visualizer::escapeOutput(Visualizer::actionHref("script", "Index", "Search.js")) ?>"></script>
</head>
<body class="search">
	<?php Visualizer::header("ホスト検索", array(), !is_null($d["entries"]) ? ($d['count'] <= $c->searchPaging ? "{$d['count']} 件" : "{$d['count']} 件中 " . (($d["page"] - 1) * $c->searchPaging + 1) . " - " . (($d["page"] - 1) * $c->searchPaging + count($d["entries"])) .  " 件") : "作品集数 {$d['subjectCount']}") ?>
	<form>
		<section class="filter">
			<ul class="params">
				<li>
					<label for="host">ホスト名</label><input type="text" name="host" id="host" value="<?=Visualizer::escapeOutput($d["host"]) ?>" />
				</li>
				<li>
					<label for="subject">作品集</label><input type="number" name="subjectBegin" id="subject" value="<?=Visualizer::escapeOutput($d["subjectBegin"]) ?>" min="1" max="<?=Visualizer::escapeOutput($d["subjectCount"]) ?>" /><span>～</span><input type="number" name="subjectEnd" value="<?=Visualizer::escapeOutput($d["subjectEnd"]) ?>" min="1" max="<?=Visualizer::escapeOutput($d["subjectCount"]) ?>" />
				</li>
				<li>
					<label for="thread">検索対象</label><label><input type="checkbox" id="thread" name="target[]" value="thread"<?=in_array("thread", $d["target"]) ? ' checked="checked"' : null ?> />作品</label><br />
					<label><input type="checkbox" name="target[]" value="evaluation"<?=in_array("evaluation", $d["target"]) ? ' checked="checked"' : null ?> />評価</label><br />
					<label><input type="checkbox" name="target[]" value="comment"<?=in_array("comment", $d["target"]) ? ' checked="checked"' : null ?> />コメント</label>
				</li>
			</ul>
			<ul class="buttons">
				<li>
					<button type="submit">
						<img src="<?=Visualizer::escapeOutput(Visualizer::actionHref("style", "searchButtonIcon.png")) ?>" alt="" />検索
					</button>
				</li>
			</ul>
		</section>
	</form>
	<div id="content">
		<?php if ($d["entries"]): ?>
			<form action="" method="post" id="entriesForm">
				<?php Visualizer::pager($d["page"], $d["pageCount"], 5, $pagerHref) ?>
				<?php Template::entries($d["entries"], true) ?>
				<?php Visualizer::pager($d["page"], $d["pageCount"], 5, $pagerHref) ?>
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
		<?php elseif (is_null($d["entries"])): ?>
			<ul class="notify info">
				<li>検索条件を指定してください</li>
				<li>ホスト名にはワイルドカードが使用できます。既定は完全一致です。</li>
				<li>対象の作品集を広げると範囲に応じて検索に時間がかかります。ご了承ください。</li>
			</ul>
		<?php else: ?>
			<p class="notify info">
				条件に合う作品は見つかりませんでした
			</p>
		<?php endif ?>
	</div>
	<?php Visualizer::footer() ?>
</body>
</html>
