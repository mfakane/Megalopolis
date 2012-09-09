<?php
$c = &Configuration::$instance;
$h = &ReadHandler::$instance;
$d = &Visualizer::$data;
$basePath = Visualizer::absoluteHref($h->subject, $h->entry->id);
$m = strlen(App::$pathInfo[$last = count(App::$pathInfo) - 1]) == 1 ? Util::escapeInput(App::$pathInfo[$last]) : "h";

function makeMenu($basePath, $current)
{
	echo '<ul>';
	
	foreach (array
	(
		"h" => "作品, home",
		"c" => "コメント, comment",
		"i" => "情報, info"
	) as $k => $v)
	{
		list($n, $i) = explode(", ", $v);
	?>
		<li>
			<a href="<?+Visualizer::absoluteHref(ReadHandler::$instance->entry->subject, ReadHandler::$instance->entry->id, $k) ?>" data-transition="none"<?if ($k == $current): ?> class="ui-btn-active"<?endif ?> data-icon="<?=$i ?>"><?+$n ?></a>
		</li>
	<?php
	}
	
	echo '</ul>';
}

Visualizer::doctype();
?>
<html lang="ja" manifest="<?+Visualizer::actionHref("manifest") ?>">
<head>
	<? Visualizer::head() ?>
	<title>
		<?+$h->entry->title ?>
	</title>
	<script src="<?+Visualizer::actionHref("script", "nehan", "nehan2-min.js") ?>"></script>
	<script src="<?+Visualizer::actionHref("script", "mobile", "Index", "Index.js") ?>"></script>
	<script src="<?+Visualizer::actionHref("script", "mobile", "Read", "Index.js") ?>"></script>
</head>
<body>
	<?if ($m == "h"): ?>
		<div id="rhome" data-role="page" data-fullscreen="true" class="read">
			<script>
				megalopolis.resetPage("index", "", true);
				megalopolis.resetPage("read", "rhome");
				megalopolis.addHistory(<?+$h->entry->id ?>, <?+$h->entry->subject ?>, '<?+$h->entry->title ?>', '<?+$h->entry->name ?>');
			</script>
			<header data-role="header" data-position="fixed" data-backbtn="false" data-theme="b">
				<h1><?+$h->entry->title ?></h1>
				<h2 class="currentPage"></h2>
				<a href="<?+Visualizer::absoluteHref($h->entry->subject) ?>" data-direction="reverse">戻る</a>
				<div data-role="controlgroup" data-type="horizontal" class="ui-btn-right">
					<button class="nextPageButton" data-theme="b">次頁</button>
					<button class="previousPageButton" data-theme="b">前頁</button>
				</div>
			</header>
			<div data-role="content" data-theme="e">
				<div class="content">
					<article class="contentWrapper">
						<?if ($c->showHeaderInsideBorder): ?>
							<header>
								<h1><?+$h->entry->title ?></h1>
								<?if ($c->showName[Configuration::ON_ENTRY]): ?>
									<address>
										<? Visualizer::convertedName($h->entry->name) ?>
									</address>
								<?endif ?>
							</header>
						<?endif ?>
						<?if ($c->useSummary && $c->showSummary[Configuration::ON_ENTRY] && !Util::isEmpty($h->entry->summary)): ?>
							<p>
								<? Visualizer::convertedSummary($h->entry->summary) ?>
							</p>
						<?endif ?>
						<div>
							<? Visualizer::convertedBody($h->thread) ?>
						</div>
						<footer>
							<h2>あとがき</h2>
							<? Visualizer::convertedAfterword($h->thread) ?>
							<?if ($c->showName[Configuration::ON_ENTRY]): ?>
								<address>
									<? Visualizer::convertedName($h->entry->name) ?>
								</address>
							<?endif ?>
						</footer>
					</article>
				</div>
			</div>
			<footer data-role="footer" data-position="fixed">
				<div data-role="navbar">
					<? makeMenu($basePath, "h") ?>
				</div>
			</footer>
			<script>
				megalopolis.read.baseHtml = null;
				megalopolis.read.renderPage();
			</script>
		</div>
	<?elseif ($m == "c"): ?>
		<div id="rcomments" data-role="page" class="read fulllist">
			<script>
				megalopolis.resetPage("read", "rcomments");
			</script>
			<header data-role="header" data-backbtn="false">
				<h1>コメント</h1>
				<?if ($c->useComments): ?>
					<a href="<?+Visualizer::absoluteHref($h->entry->subject, $h->entry->id, "p") ?>" data-icon="plus" data-iconpos="notext" data-transition="slideup" class="ui-btn-right">評価する</a>
				<?endif ?>
			</header>
			<div data-role="content">
				<ul data-role="listview">
					<?if ($c->showComment[Configuration::ON_ENTRY]): ?>
						<?foreach ($h->thread->comments as $i): ?>
							<li>
								<?if ($c->showName[Configuration::ON_COMMENT]): ?>
									<?+$i->name ?>
								<?endif ?>
								<p class="ui-li-aside">
									<date><?+Visualizer::formatDateTime($i->dateTime) ?></date>
									<?if ($i->evaluation && $c->showPoint[Configuration::ON_COMMENT]): ?>
										<br /><?+$i->evaluation->point ?>
									<?endif ?>
								</p>
								<p><? Visualizer::convertedSummary($i->body) ?></p>
							</li>
						<?endforeach ?>
					<?endif ?>
				</ul>
			</div>
			<footer data-role="footer" data-position="fixed">
				<div data-role="navbar">
					<? makeMenu($basePath, "c") ?>
				</div>
			</footer>
		</div>
	<?elseif ($m == "p"): ?>
		<form id="rpost" data-role="page" class="read fulllist" action="<?+Util::withMobileUniqueIDRequestSuffix(Visualizer::absoluteHref($h->subject, $h->entry->id, "comment")) ?>" action="post">
			<script>
				megalopolis.resetPage("read", "rpost");
				$("#rpost").one("pageshow", function() { megalopolis.read.adjustTextBox('<?=Visualizer::absoluteHref($h->subject, $h->entry->id, "c") ?>'); });
			</script>
			<header data-role="header" data-backbtn="false">
				<h1>評価する</h1>
				<a href="#" data-rel="back">キャンセル</a>
				<div class="ui-btn-right">
					<input type="submit" value="送信" data-theme="c" />
				</div>
			</header>
			<div data-role="content">
				<ul data-role="listview">
					<li>
						<label for="nameTextBox">名前: </label><input type="text" name="name" id="nameTextBox" value="<?+Cookie::getCookie(Cookie::NAME_KEY) ?>" />
					</li>
					<li>
						<label for="mailTextBox">メール: </label><input type="text" name="mail" id="mailTextBox" value="<?+Cookie::getCookie(Cookie::MAIL_KEY) ?>" />
					</li>
					<li>
						<label for="passwordTextBox">削除キー: </label><input type="text" name="password" id="passwordTextBox" value="<?+Cookie::getCookie(Cookie::PASSWORD_KEY) ?>" />
					</li>
					<?if (!Util::isEmpty($c->postPassword)): ?>
						<li>
							<label for="postPasswordTextBox">投稿キー: </label><input type="text" name="postPassword" id="postPasswordTextBox" />
						</li>
					<?endif ?>
					<?if ($c->useCommentPoints()): ?>
						<li>
							<label for="pointComboBox">評価: </label>
							<select name="point" id="pointComboBox">
								<?foreach ($c->commentPointMap as $i): ?>
									<?if ($i > 0): ?>
										<option value="<?+$i ?>"<?=ReadHandler::param("point") == $i ? ' selected="selected"' : null ?>><?+$i ?> 点</option>
									<?endif ?>
								<?endforeach ?>
								<option value="0"<?=!isset($_POST["point"]) ? ' selected="selected"' : null ?>>無評価</option>
								<?foreach ($c->commentPointMap as $i): ?>
									<?if ($i < 0): ?>
										<option value="<?+$i ?>"<?=ReadHandler::param("point") == $i ? ' selected="selected"' : null ?>><?+$i ?> 点</option>
									<?endif ?>
								<?endforeach ?>
							</select>
						</li>
					<?endif ?>
					<li>
						<textarea name="body"></textarea>
					</li>
				</ul>
			</div>
		</form>
	<?else: ?>
		<div id="rinfo" data-role="page" class="read">
			<script>
				megalopolis.resetPage("read", "rinfo");
			</script>
			<header data-role="header" data-backbtn="false">
				<h1>情報</h1>
			</header>
			<div data-role="content">
				<h2>
					<?+$h->entry->title ?>
				</h2>
				<ul data-role="listview" data-inset="true" class="deflist">
					<?if ($c->showName[Configuration::ON_ENTRY]): ?>
						<li>
							<h3>名前</h3>
							<a href="<?+Visualizer::absoluteHref("author", $h->entry->name) ?>"><? Visualizer::convertedName($h->entry->name) ?></a>
						</li>
					<?endif ?>
					<li>
						<h3>投稿日時</h3>
						<?+Visualizer::formatDateTime($h->entry->dateTime) ?>
					</li>
					<li>
						<h3>更新日時</h3>
						<?+Visualizer::formatDateTime($h->entry->lastUpdate) ?>
					</li>
					<?if ($c->showPoint[Configuration::ON_ENTRY]): ?>
						<li>
							<h3>POINT</h3>
							<?+$h->entry->points ?>
						</li>
					<?endif ?>
					<?if ($c->showRate[Configuration::ON_ENTRY]): ?>
						<li>
							<h3>Rate</h3>
							<?+$h->entry->rate ?>
						</li>
					<?endif ?>
					<?if ($c->showSize[Configuration::ON_ENTRY]): ?>
						<li>
							<h3>サイズ</h3>
							<?+$h->entry->size ?>KB
						</li>
					<?endif ?>
				</ul>
				<?if ($h->entry->tags && $c->showTags[Configuration::ON_ENTRY]): ?>
					<h2>タグ</h2>
					<ul data-role="listview" data-inset="true">
						<?foreach ($h->entry->tags as $i): ?>
							<li>
								<a href="<?+Visualizer::absoluteHref("tag", $i) ?>"><?+$i ?></a>
							</li>
						<?endforeach ?>
					</ul>
				<?endif ?>
			</div>
			<footer data-role="footer" data-position="fixed">
				<div data-role="navbar">
					<? makeMenu($basePath, "i") ?>
				</div>
			</footer>
		</div>
	<?endif ?>
</body>
</html>