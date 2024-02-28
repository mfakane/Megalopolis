<?php
namespace Megalopolis;

$c = &Configuration::$instance;
$h = &ReadHandler::$instance;
$d = &Visualizer::$data;

if (!isset($h->entry) || !isset($h->thread)) throw new ApplicationException("Thread not found.");

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
$maxPage = ceil(mb_strlen($h->thread->body ?? "") / $paging);
$commentPaging = 25;
$maxCommentPage = ceil(count($h->thread->comments) / $commentPaging);

$length = $paging;
$offset = $h->page * $paging - $paging;

if ($h->page > 1 && ($idx = mb_strrpos(mb_substr($h->thread->body ?? "", $offset - $paging, $length), "\r\n")) !== false)
{
	$offset -= $paging - $idx - 2;
	$length += $paging - $idx - 2;
}

if ($h->page < $maxPage && ($idx = mb_strrpos(mb_substr($h->thread->body ?? "", $offset, $length), "\r\n")) !== false)
	$length = $idx;
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<title>
		<?=Visualizer::escapeOutput($h->entry->title) ?>
		<?php if ($c->showName[Configuration::ON_ENTRY]): ?>
			作者: <?php Visualizer::convertedName($h->entry->name) ?>
		<?php endif ?>
	</title>
</head>
<body>
	<h1>
		<?=Visualizer::escapeOutput($h->entry->title) ?>
	</h1>
	<?php if ($m == "i"): ?>
		<?php if ($c->showName[Configuration::ON_ENTRY]): ?>
			<?php Visualizer::convertedName($h->entry->name) ?>
		<?php endif ?>
		<?php if ($h->page == 1): ?>
			<div class="info">
				<?php if ($c->showReadCount[Configuration::ON_ENTRY]): ?>
					閲覧: <?=Visualizer::escapeOutput($h->entry->readCount) ?>
				<?php endif ?>
				<?php if ($c->showReadCount[Configuration::ON_ENTRY]): ?>
					評価: <?=Visualizer::escapeOutput($c->pointMap && $c->commentPointMap ? "{$h->entry->commentedEvaluationCount}/{$h->entry->evaluationCount}" : $h->entry->evaluationCount) ?>
				<?php endif ?>
				<?php if ($c->showComment[Configuration::ON_ENTRY]): ?>
					コメント: <?=Visualizer::escapeOutput($h->entry->commentCount) ?>
				<?php endif ?>
				<?php if ($c->showPoint[Configuration::ON_ENTRY]): ?>
					POINT: <?=Visualizer::escapeOutput($h->entry->points) ?>
				<?php endif ?>
				<?php if ($c->showRate[Configuration::ON_ENTRY]): ?>
					Rate: <?=Visualizer::escapeOutput($h->entry->rate) ?>
				<?php endif ?>
			</div>
		<?php endif ?>
		<?php Visualizer::pager($h->page, $maxPage, 5, Visualizer::actionHref($h->entry->subject, $h->entry->id) . "/") ?>
		<?php if ($h->page == 1 && $c->useSummary && $c->showSummary[Configuration::ON_ENTRY] && !Util::isEmpty($h->entry->summary)): ?>
			<div class="summary">
				<?php Visualizer::convertedSummary($h->entry->summary) ?>
			</div>
		<?php endif ?>
		<div class="content">
			<?php Visualizer::convertedBody($h->thread, null, $offset, $length) ?>
		</div>
		<?php if ($h->page == $maxPage): ?>
			<div class="afterword">
				<?php Visualizer::convertedAfterword($h->thread) ?>
			</div>
			<?php if ($c->pointMap): ?>
				<form class="content" action="<?=Visualizer::escapeOutput(Util::withMobileUniqueIDRequestSuffix(Visualizer::actionHref($h->subject, $h->entry->id, "evaluate"))) ?>" method="post">
					<h2>簡易評価</h2>
					<?php if ($d && App::$actionName == "evaluate"): ?>
						<ul>
							<?php foreach (Visualizer::$data as $i): ?>
								<li>
									<?=Visualizer::escapeOutput($i) ?>
								</li>
							<?php endforeach ?>
						</ul>
					<?php endif ?>
					<?php foreach (array_reverse($c->pointMap) as $i): ?>
						<input type="submit" name="point" value="<?=Visualizer::escapeOutput($i) ?>" />
					<?php endforeach ?>
					<?php if (!Util::isEmpty($c->postPassword)): ?>
						投稿キー<input type="password" name="postPassword" id="postPassword" />
					<?php endif ?>
					<?php if ($c->useComments): ?>
						<br />
						<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->entry->subject, $h->entry->id, "p")) ?>">またはコメント</a>
					<?php endif ?>
				</form>
			<?php endif ?>
		<?php else: ?>
			<?php Visualizer::pager($h->page, $maxPage, 5, Visualizer::actionHref($h->entry->subject, $h->entry->id) . "/") ?>
		<?php endif ?>
	<?php elseif ($m == "c"): ?>
		コメント&nbsp;<a href="#menu">メニューへ</a>
		<div class="info">
			<?php if ($c->showReadCount[Configuration::ON_ENTRY]): ?>
				評価: <?=Visualizer::escapeOutput($c->pointMap && $c->commentPointMap ? "{$h->entry->commentedEvaluationCount}/{$h->entry->evaluationCount}" : $h->entry->evaluationCount) ?>
			<?php endif ?>
			<?php if ($c->showComment[Configuration::ON_ENTRY]): ?>
				コメント: <?=Visualizer::escapeOutput($h->entry->commentCount) ?>
			<?php endif ?>
			<?php if ($c->showPoint[Configuration::ON_ENTRY]): ?>
				POINT: <?=Visualizer::escapeOutput($h->entry->points) ?>
			<?php endif ?>
		</div>
		<?php Visualizer::pager($h->page, $maxCommentPage, 5, Visualizer::actionHref($h->entry->subject, $h->entry->id, "c") . "/") ?>
		<ul class="entries">
			<?php if ($c->showComment[Configuration::ON_ENTRY] && $h->thread->comments): ?>
				<?php foreach (array_slice($h->thread->comments, $h->page * $commentPaging - $commentPaging, $commentPaging) as $i): ?>
					<li>
						<?php if (isset($c->showName[Configuration::ON_COMMENT]) && $c->showName[Configuration::ON_COMMENT]): ?>
							<span class="name"><?php Visualizer::convertedName($i->name) ?></span><br />
						<?php endif ?>
						<span class="dateTime"><?=Visualizer::escapeOutput(Visualizer::formatShortDateTime($i->dateTime)) ?></span>
						<span class="points">
							<?php if (isset($c->showPoint[Configuration::ON_COMMENT]) && $c->showPoint[Configuration::ON_COMMENT]): ?>
								<?=Visualizer::escapeOutput($i->evaluation ? $i->evaluation->point : "無評価") ?>
							<?php endif ?>&nbsp;</span><br />
						<?php Visualizer::convertedSummary($i->body) ?>
					</li>
				<?php endforeach ?>
			<?php else: ?>
				<li>コメントはありません</li>
			<?php endif ?>
		</ul>
		<?php Visualizer::pager($h->page, $maxCommentPage, 5, Visualizer::actionHref($h->entry->subject, $h->entry->id, "c") . "/") ?>
	<?php else: ?>
		<?php if ($c->useComments): ?>
			<form class="content" action="<?=Visualizer::escapeOutput(Util::withMobileUniqueIDRequestSuffix(Visualizer::actionHref($h->subject, $h->entry->id, "comment"))) ?>" method="post">
				<?php if ($d && App::$actionName == "comment"): ?>
					<ul>
						<?php foreach (Visualizer::$data as $i): ?>
							<li>
								<?=Visualizer::escapeOutput($i) ?>
							</li>
						<?php endforeach ?>
					</ul>
				<?php endif ?>
				<div>
					名前<br />
					<input type="text" name="name" value="<?=Visualizer::escapeOutput(ReadHandler::param("name", Cookie::getCookie(Cookie::NAME_KEY))) ?>" /><br />
					メール<br />
					<input type="text" name="mail" value="<?=Visualizer::escapeOutput(ReadHandler::param("mail", Cookie::getCookie(Cookie::MAIL_KEY))) ?>" /><br />
					削除キー<br />
					<input type="password" name="password" value="<?=Visualizer::escapeOutput(ReadHandler::param("password", Cookie::getCookie(Cookie::PASSWORD_KEY))) ?>" /><br />
					<?php if (!Util::isEmpty($c->postPassword)): ?>
						投稿キー<br />
						<input type="password" name="postPassword" /><br />
					<?php endif ?>
					<?php if ($c->useCommentPoints()): ?>
						評価<br />
						<select name="point">
							<?php foreach ($c->commentPointMap as $i): ?>
								<?php if ($i > 0): ?>
									<option value="<?=Visualizer::escapeOutput($i) ?>"<?=ReadHandler::param("point") == $i ? ' selected="selected"' : null ?>><?=Visualizer::escapeOutput($i) ?> 点</option>
								<?php endif ?>
							<?php endforeach ?>
							<option value="0"<?=!ReadHandler::param("point") ? ' selected="selected"' : null ?>>無評価</option>
							<?php foreach ($c->commentPointMap as $i): ?>
								<?php if ($i < 0): ?>
									<option value="<?=Visualizer::escapeOutput($i) ?>"<?=ReadHandler::param("point") == $i ? ' selected="selected"' : null ?>><?=Visualizer::escapeOutput($i) ?> 点</option>
								<?php endif ?>
							<?php endforeach ?>
						</select><br />
					<?php endif ?>
					本文<br />
					<textarea name="body"><?=Visualizer::escapeOutput(ReadHandler::param("body")) ?></textarea><br />
					<input type="submit" value="送信" />
				</div>
			</form>
		<?php else: ?>
			<p class="content">
				コメントは無効です
			</p>
		<?php endif ?>
	<?php endif ?>
	<ul class="menu">
		<a id="menu" name="menu">メニュー</a>
		<?php if ($m == "i"): ?>
			<?php if ($h->page < $maxPage): ?>
				<li>
					[1]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->entry->subject, $h->entry->id, $h->page + 1)) ?>" accesskey="1">次</a>
				</li>
			<?php endif ?>
			<?php if ($h->page > 1): ?>
				<li>
					[2]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->entry->subject, $h->entry->id, $h->page - 1)) ?>" accesskey="2">前</a>
				</li>
			<?php endif ?>
			<li>
				[3]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->entry->subject, $h->entry->id, "c")) ?>" accesskey="3">コメント一覧</a>
			</li>
			<li>
				[4]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->entry->subject, $h->entry->id, "p")) ?>" accesskey="4">コメントする</a>
			</li>
		<?php elseif ($m == "c"): ?>
			<?php if ($h->page < $maxCommentPage): ?>
				<li>
					[1]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->entry->subject, $h->entry->id, "c", $h->page + 1)) ?>" accesskey="1">次</a>
				</li>
			<?php endif ?>
			<?php if ($h->page > 1): ?>
				<li>
					[2]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->entry->subject, $h->entry->id, "c", $h->page - 1)) ?>" accesskey="2">前</a>
				</li>
			<?php endif ?>
			<li>
				[3]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->entry->subject, $h->entry->id)) ?>" accesskey="3">本文</a>
			</li>
			<li>
				[4]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->entry->subject, $h->entry->id, "p")) ?>" accesskey="4">コメントする</a>
			</li>
		<?php else: ?>
			<li>
				[3]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->entry->subject, $h->entry->id, "c")) ?>" accesskey="3">コメント一覧</a>
			</li>
			<li>
				[4]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->entry->subject, $h->entry->id)) ?>" accesskey="4">本文</a>
			</li>
		<?php endif ?>
		<li>
			[0]<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->entry->subject)) ?>" accesskey="0">作品集へ戻る</a>
		</li>
	</ul>
</body>
</html>
