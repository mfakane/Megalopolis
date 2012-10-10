<?php
$h = UtilHandler::$instance;
$c = &Configuration::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();
App::load(VISUALIZER_DIR . "Template/Index");

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
	<? Visualizer::head() ?>
	<link rel="search" href="<?+Visualizer::actionHrefArray(array("search")) ?>" />
	<?if ($d["entries"]): ?>
		<?if ($d["page"] > 1): ?>
			<link rel="prev" href="<?+$pagerHref . ($d["page"] - 1) ?>" />
		<?endif ?>
		<?if ($d["page"] < $d["pageCount"]): ?>
			<link rel="next" href="<?+$pagerHref . ($d["page"] + 1) ?>" />
		<?endif ?>
	<?endif ?>
	<title>ホスト検索 - <?+$c->title ?></title>
	<script src="<?+Visualizer::actionHref("script", "Index", "Index.js") ?>"></script>
	<script src="<?+Visualizer::actionHref("script", "Index", "Search.js") ?>"></script>
</head>
<body class="search">
	<? Visualizer::header("ホスト検索", array(), !is_null($d["entries"]) ? ($d['count'] <= $c->searchPaging ? "{$d['count']} 件" : "{$d['count']} 件中 " . (($d["page"] - 1) * $c->searchPaging + 1) . " - " . (($d["page"] - 1) * $c->searchPaging + count($d["entries"])) .  " 件") : "作品集数 {$d['subjectCount']}") ?>
	<form>
		<section class="filter">
			<ul class="params">
				<li>
					<label for="host">ホスト名</label><input type="text" name="host" id="host" value="<?+$d["host"] ?>" />
				</li>
				<li>
					<label for="subject">作品集</label><input type="number" name="subjectBegin" id="subject" value="<?+$d["subjectBegin"] ?>" min="1" max="<?+$d["subjectCount"] ?>" /><span>～</span><input type="number" name="subjectEnd" value="<?+$d["subjectEnd"] ?>" min="1" max="<?+$d["subjectCount"] ?>" />
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
						<img src="<?+Visualizer::actionHref("style", "searchButtonIcon.png") ?>" alt="" />検索
					</button>
				</li>
			</ul>
		</section>
	</form>
	<div id="content">
		<?if ($d["entries"]): ?>
			<form action="" method="post" id="entriesForm">
				<? Visualizer::pager($d["page"], $d["pageCount"], 5, $pagerHref) ?>
				<? entries($d["entries"], true) ?>
				<? Visualizer::pager($d["page"], $d["pageCount"], 5, $pagerHref) ?>
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
		<?elseif (is_null($d["entries"])): ?>
			<ul class="notify info">
				<li>検索条件を指定してください</li>
				<li>ホスト名にはワイルドカードが使用できます。既定は完全一致です。</li>
				<li>対象の作品集を広げると範囲に応じて検索に時間がかかります。ご了承ください。</li>
			</ul>
		<?else: ?>
			<p class="notify info">
				条件に合う作品は見つかりませんでした
			</p>
		<?endif ?>
	</div>
	<? Visualizer::footer() ?>
</body>
</html>