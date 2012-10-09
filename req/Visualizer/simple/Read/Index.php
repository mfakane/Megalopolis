<?php
$c = &Configuration::$instance;
$h = &ReadHandler::$instance;
$d = &Visualizer::$data;
Visualizer::doctype();

if (App::$actionName == "comment")
	$m = "p";
else if (App::$pathInfo &&
	isset(App::$pathInfo[2]) &&
	Util::isLength(App::$pathInfo[2], 1) &&
	!intval(App::$pathInfo[2]))
	$m = App::$pathInfo[2];
else
	$m = "i";

if (isset(App::$pathInfo[3]) &&
	$v = intval(App::$pathInfo[3]))
	$h->page = max($v, 1);

$paging = 8000;
$maxPage = ceil(mb_strlen($h->thread->body) / $paging);
$commentPaging = 25;
$maxCommentPage = ceil(count($h->thread->comments) / $commentPaging);

$length = $paging;
$offset = $h->page * $paging - $paging;

if ($h->page > 1 && ($idx = mb_strrpos(mb_substr($h->thread->body, $offset - $paging, $length), "\r\n")) !== false)
{
	$offset -= $paging - $idx - 2;
	$length += $paging - $idx - 2;
}

if ($h->page < $maxPage && ($idx = mb_strrpos(mb_substr($h->thread->body, $offset, $length), "\r\n")) !== false)
	$length = $idx;
?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<title>
		<?+$h->entry->title ?>
		<?if ($c->showName[Configuration::ON_ENTRY]): ?>
			作者: <? Visualizer::convertedName($h->entry->name) ?>
		<?endif ?>
	</title>
</head>
<body>
	<h1>
		<?+$h->entry->title ?>
	</h1>
	<?if ($m == "i"): ?>
		<?if ($c->showName[Configuration::ON_ENTRY]): ?>
			<? Visualizer::convertedName($h->entry->name) ?>
		<?endif ?>
		<?if ($h->page == 1): ?>
			<div class="info">
				<?if ($c->showReadCount[Configuration::ON_ENTRY]): ?>
					閲覧: <?+$h->entry->readCount ?>
				<?endif ?>
				<?if ($c->showReadCount[Configuration::ON_ENTRY]): ?>
					評価: <?+$c->pointMap && $c->commentPointMap ? "{$h->entry->commentedEvaluationCount}/{$h->entry->evaluationCount}" : $h->entry->evaluationCount ?>
				<?endif ?>
				<?if ($c->showComment[Configuration::ON_ENTRY]): ?>
					コメント: <?+$h->entry->commentCount ?>
				<?endif ?>
				<?if ($c->showPoint[Configuration::ON_ENTRY]): ?>
					POINT: <?+$h->entry->points ?>
				<?endif ?>
				<?if ($c->showRate[Configuration::ON_ENTRY]): ?>
					Rate: <?+$h->entry->rate ?>
				<?endif ?>
			</div>
		<?endif ?>
		<? Visualizer::pager($h->page, $maxPage, 5, Visualizer::actionHref($h->entry->subject, $h->entry->id) . "/") ?>
		<?if ($h->page == 1 && $c->useSummary && $c->showSummary[Configuration::ON_ENTRY] && !Util::isEmpty($h->entry->summary)): ?>
			<div class="summary">
				<? Visualizer::convertedSummary($h->entry->summary) ?>
			</div>
		<?endif ?>
		<div class="content">
			<? Visualizer::convertedBody($h->thread, null, $offset, $length) ?>
		</div>
		<?if ($h->page == $maxPage): ?>
			<div class="afterword">
				<? Visualizer::convertedAfterword($h->thread) ?>
			</div>
			<?if ($c->pointMap): ?>
				<form class="content" action="<?+Util::withMobileUniqueIDRequestSuffix(Visualizer::actionHref($h->subject, $h->entry->id, "evaluate")) ?>" method="post">
					<h2>簡易評価</h2>
					<?if ($d && App::$actionName == "evaluate"): ?>
						<ul>
							<?foreach (Visualizer::$data as $i): ?>
								<li>
									<?+$i ?>
								</li>
							<?endforeach ?>
						</ul>
					<?endif ?>
					<?foreach (array_reverse($c->pointMap) as $i): ?>
						<input type="submit" name="point" value="<?+$i ?>" />
					<?endforeach ?>
					<?if (!Util::isEmpty($c->postPassword)): ?>
						投稿キー<input type="password" name="postPassword" id="postPassword" />
					<?endif ?>
					<?if ($c->useComments): ?>
						<br />
						<a href="<?+Visualizer::actionHref($h->entry->subject, $h->entry->id, "p") ?>">またはコメント</a>
					<?endif ?>
				</form>
			<?endif ?>
		<?else: ?>
			<? Visualizer::pager($h->page, $maxPage, 5, Visualizer::actionHref($h->entry->subject, $h->entry->id) . "/") ?>
		<?endif ?>
	<?elseif ($m == "c"): ?>
		コメント&nbsp;<a href="#menu">メニューへ</a>
		<div class="info">
			<?if ($c->showReadCount[Configuration::ON_ENTRY]): ?>
				評価: <?+$c->pointMap && $c->commentPointMap ? "{$h->entry->commentedEvaluationCount}/{$h->entry->evaluationCount}" : $h->entry->evaluationCount ?>
			<?endif ?>
			<?if ($c->showComment[Configuration::ON_ENTRY]): ?>
				コメント: <?+$h->entry->commentCount ?>
			<?endif ?>
			<?if ($c->showPoint[Configuration::ON_ENTRY]): ?>
				POINT: <?+$h->entry->points ?>
			<?endif ?>
		</div>
		<? Visualizer::pager($h->page, $maxCommentPage, 5, Visualizer::actionHref($h->entry->subject, $h->entry->id, "c") . "/") ?>
		<ul class="entries">
			<?if ($c->showComment[Configuration::ON_ENTRY] && $h->thread->comments): ?>
				<?foreach (array_slice($h->thread->comments, $h->page * $commentPaging - $commentPaging, $commentPaging) as $i): ?>
					<li>
						<?if ($c->showName[Configuration::ON_COMMENT]): ?>
							<span class="name"><? Visualizer::convertedName($i->name) ?></span><br />
						<?endif ?>
						<span class="dateTime"><?+Visualizer::formatShortDateTime($i->dateTime) ?></span>
						<span class="points">
							<?if ($c->showPoint[Configuration::ON_COMMENT]): ?>
								<?+$i->evaluation ? $i->evaluation->point : "無評価" ?>
							<?endif ?>&nbsp;</span><br />
						<? Visualizer::convertedSummary($i->body) ?>
					</li>
				<?endforeach ?>
			<?else: ?>
				<li>コメントはありません</li>
			<?endif ?>
		</ul>
		<? Visualizer::pager($h->page, $maxCommentPage, 5, Visualizer::actionHref($h->entry->subject, $h->entry->id, "c") . "/") ?>
	<?else: ?>
		<?if ($c->useComments): ?>
			<form class="content" action="<?+Util::withMobileUniqueIDRequestSuffix(Visualizer::actionHref($h->subject, $h->entry->id, "comment")) ?>" method="post">
				<?if ($d && App::$actionName == "comment"): ?>
					<ul>
						<?foreach (Visualizer::$data as $i): ?>
							<li>
								<?+$i ?>
							</li>
						<?endforeach ?>
					</ul>
				<?endif ?>
				<div>
					名前<br />
					<input type="text" name="name" value="<?+ReadHandler::param("name", Cookie::getCookie(Cookie::NAME_KEY)) ?>" /><br />
					メール<br />
					<input type="text" name="mail" value="<?+ReadHandler::param("mail", Cookie::getCookie(Cookie::MAIL_KEY)) ?>" /><br />
					削除キー<br />
					<input type="password" name="password" value="<?+ReadHandler::param("password", Cookie::getCookie(Cookie::PASSWORD_KEY)) ?>" /><br />
					<?if (!Util::isEmpty($c->postPassword)): ?>
						投稿キー<br />
						<input type="password" name="postPassword" /><br />
					<?endif ?>
					<?if ($c->useCommentPoints()): ?>
						評価<br />
						<select name="point">
							<?foreach ($c->commentPointMap as $i): ?>
								<?if ($i > 0): ?>
									<option value="<?+$i ?>"<?=ReadHandler::param("point") == $i ? ' selected="selected"' : null ?>><?+$i ?> 点</option>
								<?endif ?>
							<?endforeach ?>
							<option value="0"<?=!ReadHandler::param("point") ? ' selected="selected"' : null ?>>無評価</option>
							<?foreach ($c->commentPointMap as $i): ?>
								<?if ($i < 0): ?>
									<option value="<?+$i ?>"<?=ReadHandler::param("point") == $i ? ' selected="selected"' : null ?>><?+$i ?> 点</option>
								<?endif ?>
							<?endforeach ?>
						</select><br />
					<?endif ?>
					本文<br />
					<textarea name="body"><?+ReadHandler::param("body")?></textarea><br />
					<input type="submit" value="送信" />
				</div>
			</form>
		<?else: ?>
			<p class="content">
				コメントは無効です
			</p>
		<?endif ?>
	<?endif ?>
	<ul class="menu">
		<a id="menu" name="menu">メニュー</a>
		<?if ($m == "i"): ?>
			<?if ($h->page < $maxPage): ?>
				<li>
					[1]<a href="<?+Visualizer::actionHref($h->entry->subject, $h->entry->id, $h->page + 1) ?>" accesskey="1">次</a>
				</li>
			<?endif ?>
			<?if ($h->page > 1): ?>
				<li>
					[2]<a href="<?+Visualizer::actionHref($h->entry->subject, $h->entry->id, $h->page - 1) ?>" accesskey="2">前</a>
				</li>
			<?endif ?>
			<li>
				[3]<a href="<?+Visualizer::actionHref($h->entry->subject, $h->entry->id, "c") ?>" accesskey="3">コメント一覧</a>
			</li>
			<li>
				[4]<a href="<?+Visualizer::actionHref($h->entry->subject, $h->entry->id, "p") ?>" accesskey="4">コメントする</a>
			</li>
		<?elseif ($m == "c"): ?>
			<?if ($h->page < $maxCommentPage): ?>
				<li>
					[1]<a href="<?+Visualizer::actionHref($h->entry->subject, $h->entry->id, "c", $h->page + 1) ?>" accesskey="1">次</a>
				</li>
			<?endif ?>
			<?if ($h->page > 1): ?>
				<li>
					[2]<a href="<?+Visualizer::actionHref($h->entry->subject, $h->entry->id, "c", $h->page - 1) ?>" accesskey="2">前</a>
				</li>
			<?endif ?>
			<li>
				[3]<a href="<?+Visualizer::actionHref($h->entry->subject, $h->entry->id) ?>" accesskey="3">本文</a>
			</li>
			<li>
				[4]<a href="<?+Visualizer::actionHref($h->entry->subject, $h->entry->id, "p") ?>" accesskey="4">コメントする</a>
			</li>
		<?else: ?>
			<li>
				[3]<a href="<?+Visualizer::actionHref($h->entry->subject, $h->entry->id, "c") ?>" accesskey="3">コメント一覧</a>
			</li>
			<li>
				[4]<a href="<?+Visualizer::actionHref($h->entry->subject, $h->entry->id) ?>" accesskey="4">本文</a>
			</li>
		<?endif ?>
		<li>
			[0]<a href="<?+Visualizer::actionHref($h->entry->subject) ?>" accesskey="0">作品集へ戻る</a>
		</li>
	</ul>
</body>
</html>