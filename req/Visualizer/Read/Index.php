<?php
namespace Megalopolis;

$c = &Configuration::$instance;
$h = &ReadHandler::$instance;
$d = &Visualizer::$data;

if (!isset($h->entry) || !isset($h->thread)) throw new ApplicationException("Thread not found.");

$isEdit = in_array(App::$actionName, array("new", "edit"));
$isAdmin = Auth::hasSession(true);
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<?php if ($c->showName[Configuration::ON_ENTRY]): ?>
		<meta name="author" content="<?=Visualizer::escapeOutput($h->entry->name) ?>" />
	<?php endif ?>
	<?php if (!Util::isEmpty($h->entry->summary) && $c->useSummary && $c->showSummary[Configuration::ON_ENTRY]): ?>
		<meta name="description" content="<?=Visualizer::escapeOutput($h->entry->summary) ?>" />
	<?php endif ?>
	<?php if ($h->entry->tags && $c->showTags[Configuration::ON_ENTRY]): ?>
		<meta name="keywords" content="<?=Visualizer::escapeOutput(implode(",", $h->entry->tags)) ?>" />
	<?php endif ?>
	<?php if (App::$actionName == "index"): ?>
		<link rel="contents" href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->subject)) ?>" />
		<?php if (isset($h->page) && $h->entry->pageCount > 1): ?>
			<?php if ($h->page > 1): ?>
				<link rel="prev" href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->subject, $h->entry->id, $h->page - 1)) ?>" />
			<?php endif ?>
			<?php if ($h->page < $h->entry->pageCount): ?>
				<link rel="next" href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->subject, $h->entry->id, $h->page + 1)) ?>" />
			<?php endif ?>
		<?php endif ?>
	<?php endif ?>
	<title>
		<?=Visualizer::escapeOutput($h->entry->title) ?>
		<?php if ($c->showName[Configuration::ON_ENTRY]): ?>
			作者: <?php Visualizer::convertedName($h->entry->name) ?>
		<?php endif ?>
	</title>
	<script src="<?=Visualizer::escapeOutput(Visualizer::actionHref("script", "taketori", "taketori.js")) ?>"></script>
	<script src="<?=Visualizer::escapeOutput(Visualizer::actionHref("script", "Read", "Index.js")) ?>"></script>
	<style>
		html
		{
			<?php if (!Util::isEmpty($h->thread->background)): ?>
				background-color: <?=Visualizer::escapeOutput($h->thread->background) ?>;
			<?php endif ?>
		}
		
		.read #body
		{
			<?php if (!Util::isEmpty($h->thread->foreground)): ?>
				color: <?=Visualizer::escapeOutput($h->thread->foreground) ?>;
			<?php endif ?>
			<?php if (!Util::isEmpty($h->thread->background)): ?>
				background-color: <?=Visualizer::escapeOutput($h->thread->background) ?>;
			<?php endif ?>
			<?php if (!Util::isEmpty($h->thread->backgroundImage)): ?>
				background-image: url('<?=Visualizer::escapeOutput((strpos($h->thread->backgroundImage ?? "", "http://") === 0  ? null : Visualizer::$basePath) . ($h->thread->backgroundImage ?? "")) ?>');
			<?php endif ?>
			<?php if (!Util::isEmpty($h->thread->border)): ?>
				border-color: <?=Visualizer::escapeOutput($h->thread->border) ?>;
			<?php endif ?>
		}
	</style>
</head>
<body class="read">
	<?php Visualizer::header($h->entry->title, App::$actionName == "index"
		? array
		(
			"{$h->subject}" => array("作品集に戻る", "returnIcon.png"),
			"{$h->subject}/{$h->entry->id}/edit" => array("編集", "editIcon.png")
		)
		: array()) ?>
	<dl class="status">
		<dd>
			<?=Visualizer::escapeOutput(Visualizer::formatDateTime($h->entry->dateTime)) ?>
		</dd>
		<dt>最終更新</dt>
		<dd>
			<time datetime="<?=Visualizer::escapeOutput(date("c", $h->entry->getLatestLastUpdate())) ?>">
				<?=Visualizer::escapeOutput(Visualizer::formatDateTime($h->entry->getLatestLastUpdate())) ?>
			</time>
		</dd>
		<?php if ($c->showSize[Configuration::ON_ENTRY]): ?>
			<dt>サイズ</dt>
			<dd>
				<?=Visualizer::escapeOutput($h->entry->size ?? 0) ?>KB
			</dd>
		<?php endif ?>
		<?php if ($c->showPages[Configuration::ON_ENTRY]): ?>
			<dt>ページ数</dt>
			<dd>
				<?=Visualizer::escapeOutput($h->entry->pageCount ?? 1) ?>
			</dd>
		<?php endif ?>
	</dl>
	<?php if ($c->showReadCount[Configuration::ON_ENTRY] ||
		  $c->showPoint[Configuration::ON_ENTRY] ||
		  $c->showRate[Configuration::ON_ENTRY]): ?>
		<dl class="status">
			<?php if ($c->showReadCount[Configuration::ON_ENTRY]): ?>
				<dt>閲覧数</dt>
				<dd>
					<?=Visualizer::escapeOutput($h->entry->readCount ?? 0) ?>
				</dd>
			<?php endif ?>
			<?php if ($c->showPoint[Configuration::ON_ENTRY] || $c->showRate[Configuration::ON_ENTRY]): ?>
				<?php if ($c->showPoint[Configuration::ON_ENTRY]): ?>
					<dt>評価数</dt>
					<dd id="evaluationCount">
						<?=Visualizer::escapeOutput($c->pointMap && $c->commentPointMap ? "{$h->entry->commentedEvaluationCount}/{$h->entry->evaluationCount}" : $h->entry->evaluationCount) ?>
					</dd>
					<dt>POINT</dt>
					<dd id="points">
						<?=Visualizer::escapeOutput($h->entry->points ?? 0) ?>
					</dd>
				<?php endif ?>
				<?php if ($c->showRate[Configuration::ON_ENTRY]): ?>
					<dt>Rate</dt>
					<dd>
						<?=Visualizer::escapeOutput(sprintf("%.2f", $h->entry->rate)) ?>
					</dd>
				<?php endif ?>
			<?php endif ?>
		</dl>
	<?php endif ?>
	<?php if ($h->entry->tags && $c->showTags[Configuration::ON_ENTRY]): ?>
		<section id="tags">
			<h2>分類タグ</h2>
			<ul>
				<?php foreach ($h->entry->tags as $i): ?>
					<li>
						<?php Visualizer::linkedTag($i) ?>
					</li>
				<?php endforeach ?>
			</ul>
		</section>
	<?php endif ?>
	<?php if ($isEdit): ?>
		<p class="notify info">
			<?=Visualizer::escapeOutput($h->page) ?> ページ目のプレビューです
		</p>
	<?php endif ?>
	<?php if (App::$actionName != "index" && $h->entry->pageCount > 1): ?>
		<form class="pagerContainer" action="<?=Visualizer::escapeOutput(App::$actionName == "new" ? Visualizer::actionHref(App::$actionName) : Visualizer::actionHref($h->subject, $h->entry->id, App::$actionName)) ?>" method="post">
			<?php Visualizer::submitPager($h->page ?? 1, $h->entry->pageCount, 10, "p") ?>
			<?php Visualizer::delegateParameters($_POST ? $_POST : $_SESSION, array("p")) ?>
		</form>
	<?php else: ?>
		<?php Visualizer::pager($h->page ?? 1, $h->entry->pageCount, 10, array(Visualizer::actionHref($h->subject, $h->entry->id) . "/", "#body")) ?>
	<?php endif ?>
	<section id="body" data-style-path="<?=Visualizer::escapeOutput(Visualizer::$basePath) ?>style/<?=Visualizer::escapeOutput($c->skin && is_file("style/{$c->skin}/horizontalIcon.png") ? "{$c->skin}/" : null) ?>" data-writing-mode="<?=intval($h->thread->writingMode) ?>" data-force-taketori="<?=$h->forceTaketori ? "true" : "false" ?>">
		<div id="verticalWrapper">
			<div id="contentWrapper">
				<?php if ($h->page == 1 && $c->showHeaderInsideBorder): ?>
					<h1>
						<?=Visualizer::escapeOutput($h->entry->title) ?>
					</h1>
					<?php if ($c->showName[Configuration::ON_ENTRY]): ?>
						<address>
							<?php if (App::$actionName == "index"): ?>
								<?php Visualizer::linkedName($h->entry->name) ?>
							<?php else: ?>
								<?php Visualizer::convertedName($h->entry->name) ?>
							<?php endif ?>
						</address>
					<?php endif ?>
					<?php if (!Util::isEmpty($h->entry->summary) && $c->useSummary && $c->showSummary[Configuration::ON_ENTRY]): ?>
						<p id="summary">
							<?php Visualizer::convertedSummary($h->entry->summary) ?>
						</p>
					<?php endif ?>
				<?php endif ?>
				<div id="content">
					<div id="contentBody">
						<?php Visualizer::convertedBody($h->thread, $h->page) ?>
					</div>
				</div>
				<?php if ($h->page == $h->entry->pageCount): ?>
					<div id="afterword">
						<div id="afterwordBody">
							<?php Visualizer::convertedAfterword($h->thread) ?>
						</div>
						<?php if ($isAdmin || $c->showName[Configuration::ON_ENTRY]): ?>
							<address>
								<?php if (App::$actionName == "index"): ?>
									<?php Visualizer::linkedName($h->entry->name) ?>
								<?php else: ?>
									<?php Visualizer::convertedName($h->entry->name) ?>
								<?php endif ?>
								<?php if (!Util::isEmpty($h->entry->mail) || !Util::isEmpty($h->entry->link)): ?>
									<br />
								<?php endif ?>
								<?php if (!Util::isEmpty($h->entry->mail)): ?>
									<a href="mailto:<?=Visualizer::escapeOutput($h->entry->mail) ?>"><?=Visualizer::escapeOutput($h->entry->mail) ?></a>
								<?php endif ?>
								<?php if (!Util::isEmpty($h->entry->mail) && !Util::isEmpty($h->entry->link)): ?>
									<br />
								<?php endif ?>
								<?php if (!Util::isEmpty($h->entry->link)): ?>
									<a href="<?=Visualizer::escapeOutput($h->entry->link) ?>"><?=Visualizer::escapeOutput($h->entry->link) ?></a>
								<?php endif ?>
								<?php if ($isAdmin): ?>
									<br />
									<span class="host"><?=Visualizer::escapeOutput($h->entry->host) ?></span>
								<?php endif ?>
							</address>
						<?php endif ?>
						<?php if (App::$actionName == "index" && (is_array($c->showTweetButton) ? $c->showTweetButton[Configuration::ON_ENTRY] : $c->showTweetButton)): ?>
							<footer>
								<?php Visualizer::tweetButton(Visualizer::absoluteHref($h->subject, $h->entry->id), $c->entryTweetButtonText, $c->entryTweetButtonHashtags, array
								(
									"[id]" => $h->entry->id,
									"[subject]" => $h->entry->subject,
									"[title]" => $h->entry->title,
									"[name]" => $h->entry->name,
								)) ?>
							</footer>
						<?php endif ?>
					</div>
				<?php endif ?>
			</div>
		</div>
	</section>
	<?php if (App::$actionName != "index" && $h->entry->pageCount > 1): ?>
		<form class="pagerContainer" action="<?=Visualizer::escapeOutput(App::$actionName == "new" ? Visualizer::actionHref(App::$actionName) : Visualizer::actionHref($h->subject, $h->entry->id, App::$actionName)) ?>" method="post">
			<?php Visualizer::submitPager($h->page ?? 1, $h->entry->pageCount, 10, "p") ?>
			<?php Visualizer::delegateParameters($_POST ? $_POST : $_SESSION, array("p")) ?>
		</form>
	<?php else: ?>
		<?php Visualizer::pager($h->page ?? 1, $h->entry->pageCount, 10, array(Visualizer::actionHref($h->subject, $h->entry->id) . "/", "#body")) ?>
	<?php endif ?>
	<?php if ($isEdit): ?>
		<section class="notify info">
			<ul class="buttons">
				<li>
					<form id="resumeForm" action="<?php Visualizer::converted(Util::withMobileUniqueIDRequestSuffix(App::$actionName == "new"
						? Visualizer::actionHref(App::$actionName)
						: Visualizer::actionHref($h->subject, $h->entry->id, App::$actionName))) ?>" method="post">
						<div>
							<button type="submit">
								<img src="<?=Visualizer::escapeOutput(Visualizer::actionHref("style", "backButtonIcon.png")) ?>" alt="" />修正
							</button>
							<?php Visualizer::delegateParameters($_POST ? $_POST : $_SESSION, array("preview", "p")) ?>
						</div>
					</form>
				</li>
				<li>
					<form id="sendForm" action="<?php Visualizer::converted(Util::withMobileUniqueIDRequestSuffix(App::$actionName == "new"
						? Visualizer::actionHref("post")
						: Visualizer::actionHref($h->subject, $h->entry->id, "post"))) ?>" method="post">
						<div>
							<button type="submit">
								送信<img src="<?=Visualizer::escapeOutput(Visualizer::actionHref("style", "sendButtonIcon.png")) ?>" class="last" alt="" />
							</button>
							<?php Visualizer::delegateParameters($_POST ? $_POST : $_SESSION, array("preview", "p")) ?>
						</div>
					</form>
				</li>
			</ul>
			間違いが無ければ [送信] をクリックし投稿します。修正すべき箇所がある場合は [修正] をクリックし編集画面に戻ります
		</section>
	<?php else: ?>
		<div id="links" data-default-evaluator="<?=$c->usePoints() && $c->useComments ? $c->defaultEvaluator : -1 ?>"></div>
		<?php if (!$c->showCommentsOnLastPageOnly || $h->page == $h->entry->pageCount): ?>
			<?php if ($c->usePoints()): ?>
				<a id="evaluateformHeadding" href="#evaluateform" class="first">簡易評価</a>
				<form id="evaluateform" action="<?=Visualizer::escapeOutput(Util::withMobileUniqueIDRequestSuffix(Visualizer::actionHref($h->subject, $h->entry->id, "evaluate"))) ?>#evaluateformHeadding" method="post">
					<?php if ($d && App::$actionName == "evaluate"): ?>
						<ul class="notify warning">
							<?php foreach (Visualizer::$data as $i): ?>
								<li>
									<?=Visualizer::escapeOutput($i) ?>
								</li>
							<?php endforeach ?>
						</ul>
					<?php endif ?>
					<ul class="buttons">
						<?php foreach (array_reverse($c->pointMap) as $i): ?>
							<li>
								<input type="submit" name="point" value="<?=Visualizer::escapeOutput($i) ?>" />
							</li>
						<?php endforeach ?>
					</ul>
					<?php if (!Util::isEmpty($c->postPassword)): ?>
						<div>
							<label for="postPassword">投稿キー</label><input type="password" name="postPassword" id="postPassword" />
						</div>
					<?php endif ?>
					<p>
						点数のボタンをクリックしコメントなしで評価します。
					</p>
				</form>
			<?php endif ?>
			<?php if ($c->useComments): ?>
				<a id="commentformHeadding" href="#commentform"<?php if (!$c->usePoints()) echo 'class="first"' ?>>コメント</a>
				<form id="commentform"
					  action="<?=Visualizer::escapeOutput(Util::withMobileUniqueIDRequestSuffix(Visualizer::actionHref($h->subject, $h->entry->id, "comment"))) ?>#commentformHeadding"
					  method="post"
					  data-default-name="<?=Visualizer::escapeOutput($c->defaultName) ?>"
					  data-use="<?=implode(" ", array_filter(array($isAdmin || $c->showName[Configuration::ON_COMMENT] ? "name" : null, $isAdmin || $c->showPoint[Configuration::ON_COMMENT] ? "points" : null))) ?>">
					<div id="commentformContent">
						<?php if ($d && App::$actionName == "comment"): ?>
							<ul class="notify warning">
								<?php foreach (Visualizer::$data as $i): ?>
									<li>
										<?=Visualizer::escapeOutput($i) ?>
									</li>
								<?php endforeach ?>
							</ul>
						<?php endif ?>
						<div>
							<div>
								<label for="name">名前</label><input type="text" name="name" id="name" value="<?=Visualizer::escapeOutput(ReadHandler::param("name", Cookie::getCookie(Cookie::NAME_KEY))) ?>"<?=$c->requireName[Configuration::ON_COMMENT] ? 'required="required"' : null ?> /><br />
								<label for="mail">メール</label><input type="email" name="mail" id="mail" value="<?=Visualizer::escapeOutput(ReadHandler::param("mail", Cookie::getCookie(Cookie::MAIL_KEY))) ?>" /><br />
								<label for="password">削除キー</label><input type="password" name="password" id="password" value="<?=Visualizer::escapeOutput(ReadHandler::param("password", Cookie::getCookie(Cookie::PASSWORD_KEY))) ?>"<?=$c->requirePassword[Configuration::ON_ENTRY] ? ' required="required"' : null ?> /><br />
								<?php if (!Util::isEmpty($c->postPassword)): ?>
									<label for="postPassword2">投稿キー</label><input type="password" name="postPassword" id="postPassword2" /><br />
								<?php endif ?>
								<?php if ($c->useCommentPoints()): ?>
									<label for="point">評価</label>
									<select name="point" id="point">
										<?php foreach ($c->commentPointMap as $i): ?>
											<?php if ($i > 0): ?>
												<option value="<?=Visualizer::escapeOutput($i) ?>"<?=ReadHandler::param("point") == $i ? ' selected="selected"' : null ?>><?=Visualizer::escapeOutput($i) ?> 点</option>
											<?php endif ?>
										<?php endforeach ?>
										<option value="0"<?=!isset($_POST["point"]) ? ' selected="selected"' : null ?>>無評価</option>
										<?php foreach ($c->commentPointMap as $i): ?>
											<?php if ($i < 0): ?>
												<option value="<?=Visualizer::escapeOutput($i) ?>"<?=ReadHandler::param("point") == $i ? ' selected="selected"' : null ?>><?=Visualizer::escapeOutput($i) ?> 点</option>
											<?php endif ?>
										<?php endforeach ?>
									</select>
								<?php endif ?>
							</div>
							<textarea name="body" class="<?=Visualizer::escapeOutput(implode(" ", array($c->useCommentPoints() ? "usePoint" : "", !Util::isEmpty($c->postPassword) ? "usePostPassword" : ""))) ?>" rows="2" cols="80"><?=Visualizer::escapeOutput(ReadHandler::param("body")) ?></textarea>
						</div>
						<ul class="buttons">
							<li>
								<button type="submit">
									<img src="<?=Visualizer::escapeOutput(Visualizer::actionHref("style", "writeButtonIcon.png")) ?>" alt="" />送信
								</button>
							</li>
						</ul>
					</div>
				</form>
			<?php endif ?>
			<?php if ($isAdmin || $c->showComment[Configuration::ON_ENTRY]): ?>
				<?php
				$arr = ($h->thread->comments) + ($h->thread->nonCommentEvaluations);
				ksort($arr);
				$arr = array_values($arr);
				?>
				<?php if ($isAdmin): ?>
					<form action="" method="post">
				<?php endif ?>
				<dl id="comments">
					<?php if (($h->thread->nonCommentEvaluations || $c->pointMap) && $c->showPoint[Configuration::ON_COMMENT]): ?>
						<?php if ($isAdmin): ?>
							<?php if ($h->thread->nonCommentEvaluations): ?>
								<?php foreach (array_filter($arr, function($_) { return $_ instanceof Evaluation; }) as $k => $i): ?>
									<dt class="evaluation">
										<input type="checkbox" name="id[]" value="<?=Visualizer::escapeOutput($i->id) ?>" />
										<?=Visualizer::escapeOutput($k + 1) ?>.
										<?php if ($i->point < 0): ?>
											<span class="point minus"><?=Visualizer::escapeOutput($i->point) ?></span>点
										<?php else: ?>
											<span class="point plus"><?=Visualizer::escapeOutput($i->point) ?></span>点
										<?php endif ?>
										<span class="name">簡易評価</span>
										<time datetime="<?=Visualizer::escapeOutput(date("c", $i->dateTime)) ?>">
											<?=Visualizer::escapeOutput(Visualizer::formatDateTime($i->dateTime)) ?>
										</time>
										<?php if ($isAdmin): ?>
											<span class="host"><?=Visualizer::escapeOutput($i->host) ?></span>
										<?php endif ?>
									</dt>
								<?php endforeach ?>
							<?php else: ?>
								<dt class="evaluation">
									0. <span class="point none">簡易評価なし</span>
								</dt>
							<?php endif ?>
						<?php else: ?>
							<dt class="evaluation">
								0.
								<?php if ($h->thread->nonCommentEvaluations): ?>
									<?php $p = array_reduce($h->thread->nonCommentEvaluations, fn(int $x, Evaluation $y) => $x + $y->point, 0) ?>
									<?php if ($p < 0): ?>
										<span class="point minus"><?=Visualizer::escapeOutput($p) ?></span>点
									<?php else: ?>
										<span class="point plus"><?=Visualizer::escapeOutput($p) ?></span>点
									<?php endif ?>
									<span class="name">簡易評価</span>
								<?php else: ?>
									<span class="point none">簡易評価なし</span>
								<?php endif ?>
							</dt>
						<?php endif ?>
					<?php endif ?>
					<?php if ($h->thread->comments): ?>
						<?php foreach (array_filter($arr, function($_) { return $_ instanceof Comment; }) as $k => $i): ?>
							<dt id="comment<?=Visualizer::escapeOutput($k + 1) ?>">
								<?php if ($isAdmin): ?>
									<input type="checkbox" name="id[]" value="<?=Visualizer::escapeOutput($i->id) ?>" />
								<?php endif ?>
								<?=Visualizer::escapeOutput($k + 1) ?>.
								<?php if ($isAdmin || $c->showPoint[Configuration::ON_COMMENT]): ?>
									<?php if ($i->evaluation): ?>
										<?php if ($i->evaluation->point < 0): ?>
											<span class="point minus"><?=Visualizer::escapeOutput($i->evaluation->point) ?></span>点
										<?php else: ?>
											<span class="point plus"><?=Visualizer::escapeOutput($i->evaluation->point) ?></span>点
										<?php endif ?>
									<?php else: ?>
										<span class="point none">無評価</span>
									<?php endif ?>
								<?php endif ?>
								<?php if ($isAdmin || $c->showName[Configuration::ON_COMMENT]): ?>
									<span class="name">
										<?php if (!Util::isEmpty($i->mail)): ?>
											<a href="mailto:<?=Visualizer::escapeOutput($i->mail) ?>">
												<?php Visualizer::convertedName($i->name) ?>
											</a>
										<?php else: ?>
											<?php Visualizer::convertedName($i->name) ?>
										<?php endif ?>
									</span>
								<?php endif ?>
								<time datetime="<?=Visualizer::escapeOutput(date("c", $i->dateTime)) ?>">
									<?=Visualizer::escapeOutput(Visualizer::formatDateTime($i->dateTime)) ?>
								</time>
								<?php if ($isAdmin): ?>
									<span class="host"><?=Visualizer::escapeOutput($i->host) ?></span>
								<?php else: ?>
									<a href="<?=Visualizer::escapeOutput(Visualizer::actionHref($h->subject, $h->entry->id, "uncomment", array("id" => $i->id))) ?>">削除</a>
								<?php endif ?>
							</dt>
							<dd>
								<?php Visualizer::convertedSummary($i->body) ?>
							</dd>
						<?php endforeach ?>
					<?php else: ?>
						<dt class="none">
							0. コメントなし
						</dt>
					<?php endif ?>
				</dl>
				<?php if ($isAdmin): ?>
						<input type="hidden" name="token" value="<?=Visualizer::escapeOutput($_SESSION[Auth::SESSION_TOKEN]) ?>" />
						<section class="admin">
							<ul class="buttons">
								<li>
									<button type="submit" class="unpost" name="admin" value="unevaluate" id="unevaluateButton">
										<img src="<?=Visualizer::escapeOutput(Visualizer::actionHref("style", "deleteButtonIcon.png")) ?>" alt="" />選択した評価を削除
									</button>
								</li>
								<li>
									<button type="submit" class="unpost" name="admin" value="uncomment" id="uncommentButton">
										<img src="<?=Visualizer::escapeOutput(Visualizer::actionHref("style", "deleteButtonIcon.png")) ?>" alt="" />選択したコメントを削除
									</button>
								</li>
							</ul>
						</section>
					</form>
				<?php endif ?>
			<?php endif ?>
		<?php else: ?>
			<section>
				<p class="commentIsOnLastPage">
					コメントは最後のページに表示されます。
				</p>
			</section>
		<?php endif ?>
	<?php endif ?>
	<?php Visualizer::footer($h->thread->background) ?>
</body>
</html>
