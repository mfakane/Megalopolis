<?php
$c = &Configuration::$instance;
$h = &ReadHandler::$instance;
$d = &Visualizer::$data;
$isEdit = in_array(App::$actionName, array("new", "edit"));
$isAdmin = Auth::hasSession(true);
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<?if ($c->showName[Configuration::ON_ENTRY]): ?>
		<meta name="author" content="<?+$h->entry->name ?>" />
	<?endif ?>
	<?if (!Util::isEmpty($h->entry->summary) && $c->useSummary && $c->showSummary[Configuration::ON_ENTRY]): ?>
		<meta name="description" content="<?+$h->entry->summary ?>" />
	<?endif ?>
	<?if ($h->entry->tags && $c->showTags[Configuration::ON_ENTRY]): ?>
		<meta name="keywords" content="<?+implode(",", $h->entry->tags) ?>" />
	<?endif ?>
	<?if (App::$actionName == "index"): ?>
		<link rel="contents" href="<?+Visualizer::actionHref($h->subject) ?>" />
		<?if ($h->entry->pageCount > 1): ?>
			<?if ($h->page > 1): ?>
				<link rel="prev" href="<?+Visualizer::actionHref($h->subject, $h->entry->id, $h->page - 1) ?>" />
			<?endif ?>
			<?if ($h->page < $h->entry->pageCount): ?>
				<link rel="next" href="<?+Visualizer::actionHref($h->subject, $h->entry->id, $h->page + 1) ?>" />
			<?endif ?>
		<?endif ?>
	<?endif ?>
	<title>
		<?+$h->entry->title ?>
		<?if ($c->showName[Configuration::ON_ENTRY]): ?>
			作者: <? Visualizer::convertedName($h->entry->name) ?>
		<?endif ?>
	</title>
	<script src="<?+Visualizer::actionHref("script", "taketori", "taketori.js") ?>"></script>
	<script src="<?+Visualizer::actionHref("script", "Read", "Index.js") ?>"></script>
	<style>
		html
		{
			<?if (!Util::isEmpty($h->thread->background)): ?>
				background-color: <?+$h->thread->background ?>;
			<?endif ?>
		}
		
		.read #body
		{
			<?if (!Util::isEmpty($h->thread->foreground)): ?>
				color: <?+$h->thread->foreground ?>;
			<?endif ?>
			<?if (!Util::isEmpty($h->thread->background)): ?>
				background-color: <?+$h->thread->background ?>;
			<?endif ?>
			<?if (!Util::isEmpty($h->thread->backgroundImage)): ?>
				background-image: url('<?+(strpos($h->thread->backgroundImage, "http://") === 0 ? null : Visualizer::$basePath) . $h->thread->backgroundImage ?>');
			<?endif ?>
			<?if (!Util::isEmpty($h->thread->border)): ?>
				border-color: <?+$h->thread->border ?>;
			<?endif ?>
		}
	</style>
</head>
<body class="read">
	<? Visualizer::header($h->entry->title, App::$actionName == "index"
		? array
		(
			"{$h->subject}" => array("作品集に戻る", "returnIcon.png"),
			"{$h->subject}/{$h->entry->id}/edit" => array("編集", "editIcon.png")
		)
		: array()) ?>
	<dl class="status">
		<dd>
			<?+Visualizer::formatDateTime($h->entry->dateTime) ?>
		</dd>
		<dt>最終更新</dt>
		<dd>
			<time datetime="<?+date("c", $h->entry->getLatestLastUpdate()) ?>">
				<?+Visualizer::formatDateTime($h->entry->getLatestLastUpdate()) ?>
			</time>
		</dd>
		<?if ($c->showSize[Configuration::ON_ENTRY]): ?>
			<dt>サイズ</dt>
			<dd>
				<?+$h->entry->size ?>KB
			</dd>
		<?endif ?>
		<?if ($c->showPages[Configuration::ON_ENTRY]): ?>
			<dt>ページ数</dt>
			<dd>
				<?+$h->entry->pageCount ?>
			</dd>
		<?endif ?>
	</dl>
	<?if ($c->showReadCount[Configuration::ON_ENTRY] ||
		  $c->showPoint[Configuration::ON_ENTRY] ||
		  $c->showRate[Configuration::ON_ENTRY]): ?>
		<dl class="status">
			<?if ($c->showReadCount[Configuration::ON_ENTRY]): ?>
				<dt>閲覧数</dt>
				<dd>
					<?+$h->entry->readCount ?>
				</dd>
			<?endif ?>
			<?if ($c->showPoint[Configuration::ON_ENTRY] || $c->showRate[Configuration::ON_ENTRY]): ?>
				<?if ($c->showPoint[Configuration::ON_ENTRY]): ?>
					<dt>評価数</dt>
					<dd id="evaluationCount">
						<?+$c->pointMap && $c->commentPointMap ? "{$h->entry->commentedEvaluationCount}/{$h->entry->evaluationCount}" : $h->entry->evaluationCount ?>
					</dd>
					<dt>POINT</dt>
					<dd id="points">
						<?+$h->entry->points ?>
					</dd>
				<?endif ?>
				<?if ($c->showRate[Configuration::ON_ENTRY]): ?>
					<dt>Rate</dt>
					<dd>
						<?+sprintf("%.2f", $h->entry->rate) ?>
					</dd>
				<?endif ?>
			<?endif ?>
		</dl>
	<?endif ?>
	<?if ($h->entry->tags && $c->showTags[Configuration::ON_ENTRY]): ?>
		<section id="tags">
			<h2>分類タグ</h2>
			<ul>
				<?foreach ($h->entry->tags as $i): ?>
					<li>
						<? Visualizer::linkedTag($i) ?>
					</li>
				<?endforeach ?>
			</ul>
		</section>
	<?endif ?>
	<?if ($isEdit): ?>
		<p class="notify info">
			<?+$h->page ?> ページ目のプレビューです
		</p>
	<?endif ?>
	<?if (App::$actionName != "index" && $h->entry->pageCount > 1): ?>
		<form class="pagerContainer" action="<?+App::$actionName == "new" ? Visualizer::actionHref(App::$actionName) : Visualizer::actionHref($h->subject, $h->entry->id, App::$actionName) ?>" method="post">
			<? Visualizer::submitPager($h->page, $h->entry->pageCount, 10, "p") ?>
			<? Visualizer::delegateParameters($_POST ? $_POST : $_SESSION, array("p")) ?>
		</form>
	<?else: ?>
		<? Visualizer::pager($h->page, $h->entry->pageCount, 10, array(Visualizer::actionHref($h->subject, $h->entry->id) . "/", "#body")) ?>
	<?endif ?>
	<section id="body" data-style-path="<?+Visualizer::$basePath ?>style/<?+$c->skin && is_file("style/{$c->skin}/horizontalIcon.png") ? "{$c->skin}/" : null ?>" data-writing-mode="<?=intval($h->thread->writingMode) ?>" data-force-taketori="<?=$h->forceTaketori ? "true" : "false" ?>">
		<div id="verticalWrapper">
			<div id="contentWrapper">
				<?if ($h->page == 1 && $c->showHeaderInsideBorder): ?>
					<h1>
						<?+$h->entry->title ?>
					</h1>
					<?if ($c->showName[Configuration::ON_ENTRY]): ?>
						<address>
							<?if (App::$actionName == "index"): ?>
								<? Visualizer::linkedName($h->entry->name) ?>
							<?else: ?>
								<? Visualizer::convertedName($h->entry->name) ?>
							<?endif ?>
						</address>
					<?endif ?>
					<?if (!Util::isEmpty($h->entry->summary) && $c->useSummary && $c->showSummary[Configuration::ON_ENTRY]): ?>
						<p id="summary">
							<? Visualizer::convertedSummary($h->entry->summary) ?>
						</p>
					<?endif ?>
				<?endif ?>
				<div id="content">
					<div id="contentBody">
						<? Visualizer::convertedBody($h->thread, $h->page) ?>
					</div>
				</div>
				<?if ($h->page == $h->entry->pageCount): ?>
					<div id="afterword">
						<div id="afterwordBody">
							<? Visualizer::convertedAfterword($h->thread) ?>
						</div>
						<?if ($isAdmin || $c->showName[Configuration::ON_ENTRY]): ?>
							<address>
								<?if (App::$actionName == "index"): ?>
									<? Visualizer::linkedName($h->entry->name) ?>
								<?else: ?>
									<? Visualizer::convertedName($h->entry->name) ?>
								<?endif ?>
								<?if (!Util::isEmpty($h->entry->mail) || !Util::isEmpty($h->entry->link)): ?>
									<br />
								<?endif ?>
								<?if (!Util::isEmpty($h->entry->mail)): ?>
									<a href="mailto:<?+$h->entry->mail ?>"><?+$h->entry->mail ?></a>
								<?endif ?>
								<?if (!Util::isEmpty($h->entry->mail) && !Util::isEmpty($h->entry->link)): ?>
									<br />
								<?endif ?>
								<?if (!Util::isEmpty($h->entry->link)): ?>
									<a href="<?+$h->entry->link ?>"><?+$h->entry->link ?></a>
								<?endif ?>
								<?if ($isAdmin): ?>
									<br />
									<span class="host"><?+$h->entry->host ?></span>
								<?endif ?>
							</address>
						<?endif ?>
						<?if (App::$actionName == "index" && (is_array($c->showTweetButton) ? $c->showTweetButton[Configuration::ON_ENTRY] : $c->showTweetButton)): ?>
							<footer>
								<? Visualizer::tweetButton(Visualizer::absoluteHref($h->subject, $h->entry->id), $c->entryTweetButtonText, $c->entryTweetButtonHashtags, array
								(
									"[id]" => $h->entry->id,
									"[subject]" => $h->entry->subject,
									"[title]" => $h->entry->title,
									"[name]" => $h->entry->name,
								)) ?>
							</footer>
						<?endif ?>
					</div>
				<?endif ?>
			</div>
		</div>
	</section>
	<?if (App::$actionName != "index" && $h->entry->pageCount > 1): ?>
		<form class="pagerContainer" action="<?+App::$actionName == "new" ? Visualizer::actionHref(App::$actionName) : Visualizer::actionHref($h->subject, $h->entry->id, App::$actionName) ?>" method="post">
			<? Visualizer::submitPager($h->page, $h->entry->pageCount, 10, "p") ?>
			<? Visualizer::delegateParameters($_POST ? $_POST : $_SESSION, array("p")) ?>
		</form>
	<?else: ?>
		<? Visualizer::pager($h->page, $h->entry->pageCount, 10, array(Visualizer::actionHref($h->subject, $h->entry->id) . "/", "#body")) ?>
	<?endif ?>
	<?if ($isEdit): ?>
		<section class="notify info">
			<ul class="buttons">
				<li>
					<form id="resumeForm" action="<? Visualizer::converted(Util::withMobileUniqueIDRequestSuffix(App::$actionName == "new"
						? Visualizer::actionHref(App::$actionName)
						: Visualizer::actionHref($h->subject, $h->entry->id, App::$actionName))) ?>" method="post">
						<div>
							<button type="submit">
								<img src="<?+Visualizer::actionHref("style", "backButtonIcon.png") ?>" alt="" />修正
							</button>
							<? Visualizer::delegateParameters($_POST ? $_POST : $_SESSION, array("preview", "p")) ?>
						</div>
					</form>
				</li>
				<li>
					<form id="sendForm" action="<? Visualizer::converted(Util::withMobileUniqueIDRequestSuffix(App::$actionName == "new"
						? Visualizer::actionHref("post")
						: Visualizer::actionHref($h->subject, $h->entry->id, "post"))) ?>" method="post">
						<div>
							<button type="submit">
								送信<img src="<?+Visualizer::actionHref("style", "sendButtonIcon.png") ?>" class="last" alt="" />
							</button>
							<? Visualizer::delegateParameters($_POST ? $_POST : $_SESSION, array("preview", "p")) ?>
						</div>
					</form>
				</li>
			</ul>
			間違いが無ければ [送信] をクリックし投稿します。修正すべき箇所がある場合は [修正] をクリックし編集画面に戻ります
		</section>
	<? else: ?>
		<div id="links" data-default-evaluator="<?=$c->usePoints() && $c->useComments ? $c->defaultEvaluator : -1 ?>"></div>
		<?if (!$c->showCommentsOnLastPageOnly || $h->page == $h->entry->pageCount): ?>
			<?if ($c->usePoints()): ?>
				<a id="evaluateformHeadding" href="#evaluateform" class="first">簡易評価</a>
				<form id="evaluateform" action="<?+Util::withMobileUniqueIDRequestSuffix(Visualizer::actionHref($h->subject, $h->entry->id, "evaluate")) ?>#evaluateformHeadding" method="post">
					<?if ($d && App::$actionName == "evaluate"): ?>
						<ul class="notify warning">
							<?foreach (Visualizer::$data as $i): ?>
								<li>
									<?+$i ?>
								</li>
							<?endforeach ?>
						</ul>
					<?endif ?>
					<ul class="buttons">
						<?foreach (array_reverse($c->pointMap) as $i): ?>
							<li>
								<input type="submit" name="point" value="<?+$i ?>" />
							</li>
						<?endforeach ?>
					</ul>
					<?if (!Util::isEmpty($c->postPassword)): ?>
						<div>
							<label for="postPassword">投稿キー</label><input type="password" name="postPassword" id="postPassword" />
						</div>
					<?endif ?>
					<p>
						点数のボタンをクリックしコメントなしで評価します。
					</p>
				</form>
			<?endif ?>
			<?if ($c->useComments): ?>
				<a id="commentformHeadding" href="#commentform"<?if (!$c->usePoints()) echo 'class="first"' ?>>コメント</a>
				<form id="commentform"
					  action="<?+Util::withMobileUniqueIDRequestSuffix(Visualizer::actionHref($h->subject, $h->entry->id, "comment")) ?>#commentformHeadding"
					  method="post"
					  data-default-name="<?+$c->defaultName ?>"
					  data-use="<?=implode(" ", array_filter(array($isAdmin || $c->showName[Configuration::ON_COMMENT] ? "name" : null, $isAdmin || $c->showPoint[Configuration::ON_COMMENT] ? "points" : null))) ?>">
					<div id="commentformContent">
						<?if ($d && App::$actionName == "comment"): ?>
							<ul class="notify warning">
								<?foreach (Visualizer::$data as $i): ?>
									<li>
										<?+$i ?>
									</li>
								<?endforeach ?>
							</ul>
						<?endif ?>
						<div>
							<div>
								<label for="name">名前</label><input type="text" name="name" id="name" value="<?+ReadHandler::param("name", Cookie::getCookie(Cookie::NAME_KEY)) ?>"<?=$c->requireName[Configuration::ON_COMMENT] ? 'required="required"' : null ?> /><br />
								<label for="mail">メール</label><input type="email" name="mail" id="mail" value="<?+ReadHandler::param("mail", Cookie::getCookie(Cookie::MAIL_KEY)) ?>" /><br />
								<label for="password">削除キー</label><input type="password" name="password" id="password" value="<?+ReadHandler::param("password", Cookie::getCookie(Cookie::PASSWORD_KEY)) ?>"<?=$c->requirePassword[Configuration::ON_ENTRY] ? ' required="required"' : null ?> /><br />
								<?if (!Util::isEmpty($c->postPassword)): ?>
									<label for="postPassword2">投稿キー</label><input type="password" name="postPassword" id="postPassword2" /><br />
								<?endif ?>
								<?if ($c->useCommentPoints()): ?>
									<label for="point">評価</label>
									<select name="point" id="point">
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
								<?endif ?>
							</div>
							<textarea name="body" class="<?+implode(" ", array($c->useCommentPoints() ? "usePoint" : "", !Util::isEmpty($c->postPassword) ? "usePostPassword" : ""))?>" rows="2" cols="80"><?+ReadHandler::param("body")?></textarea>
						</div>
						<ul class="buttons">
							<li>
								<button type="submit">
									<img src="<?+Visualizer::actionHref("style", "writeButtonIcon.png") ?>" alt="" />送信
								</button>
							</li>
						</ul>
					</div>
				</form>
			<?endif ?>
			<?if ($isAdmin || $c->showComment[Configuration::ON_ENTRY]): ?>
				<?php
				$arr = $h->thread->comments + $h->thread->nonCommentEvaluations;
				ksort($arr);
				$arr = array_values($arr);
				?>
				<?if ($isAdmin): ?>
					<form action="" method="post">
				<?endif ?>
				<dl id="comments">
					<?if (($h->thread->nonCommentEvaluations || $c->pointMap) && $c->showPoint[Configuration::ON_COMMENT]): ?>
						<?if ($isAdmin): ?>
							<?if ($h->thread->nonCommentEvaluations): ?>
								<?foreach (array_filter($arr, create_function('$_', 'return $_ instanceof Evaluation;')) as $k => $i): ?>
									<dt class="evaluation">
										<input type="checkbox" name="id[]" value="<?+$i->id ?>" />
										<?+$k + 1 ?>.
										<?if ($i->point < 0): ?>
											<span class="point minus"><?+$i->point ?></span>点
										<?else: ?>
											<span class="point plus"><?+$i->point ?></span>点
										<?endif ?>
										<span class="name">簡易評価</span>
										<time datetime="<?+date("c", $i->dateTime) ?>">
											<?+Visualizer::formatDateTime($i->dateTime) ?>
										</time>
										<?if ($isAdmin): ?>
											<span class="host"><?+$i->host ?></span>
										<?endif ?>
									</dt>
								<?endforeach ?>
							<?else: ?>
								<dt class="evaluation">
									0. <span class="point none">簡易評価なし</span>
								</dt>
							<?endif ?>
						<?else: ?>
							<dt class="evaluation">
								0.
								<?if ($h->thread->nonCommentEvaluations): ?>
									<? $p = array_reduce($h->thread->nonCommentEvaluations, create_function('$x, $y', 'return $x + $y->point;'), 0) ?>
									<?if ($p < 0): ?>
										<span class="point minus"><?+$p ?></span>点
									<?else: ?>
										<span class="point plus"><?+$p ?></span>点
									<?endif ?>
									<span class="name">簡易評価</span>
								<?else: ?>
									<span class="point none">簡易評価なし</span>
								<?endif ?>
							</dt>
						<?endif ?>
					<?endif ?>
					<?if ($h->thread->comments): ?>
						<?foreach (array_filter($arr, create_function('$_', 'return $_ instanceof Comment;')) as $k => $i): ?>
							<dt id="comment<?+$k + 1 ?>">
								<?if ($isAdmin): ?>
									<input type="checkbox" name="id[]" value="<?+$i->id ?>" />
								<?endif ?>
								<?+$k + 1 ?>.
								<?if ($isAdmin || $c->showPoint[Configuration::ON_COMMENT]): ?>
									<?if ($i->evaluation): ?>
										<?if ($i->evaluation->point < 0): ?>
											<span class="point minus"><?+$i->evaluation->point ?></span>点
										<?else: ?>
											<span class="point plus"><?+$i->evaluation->point ?></span>点
										<?endif ?>
									<?else: ?>
										<span class="point none">無評価</span>
									<?endif ?>
								<?endif ?>
								<?if ($isAdmin || $c->showName[Configuration::ON_COMMENT]): ?>
									<span class="name">
										<?if (!Util::isEmpty($i->mail)): ?>
											<a href="mailto:<?+$i->mail ?>">
												<? Visualizer::convertedName($i->name) ?>
											</a>
										<?else: ?>
											<? Visualizer::convertedName($i->name) ?>
										<?endif ?>
									</span>
								<?endif ?>
								<time datetime="<?+date("c", $i->dateTime) ?>">
									<?+Visualizer::formatDateTime($i->dateTime) ?>
								</time>
								<?if ($isAdmin): ?>
									<span class="host"><?+$i->host ?></span>
								<?else: ?>
									<a href="<?+Visualizer::actionHref($h->subject, $h->entry->id, "uncomment", array("id" => $i->id)) ?>">削除</a>
								<?endif ?>
							</dt>
							<dd>
								<? Visualizer::convertedSummary($i->body) ?>
							</dd>
						<?endforeach ?>
					<?else: ?>
						<dt class="none">
							0. コメントなし
						</dt>
					<?endif ?>
				</dl>
				<?if ($isAdmin): ?>
						<input type="hidden" name="token" value="<?+$_SESSION[Auth::SESSION_TOKEN] ?>" />
						<section class="admin">
							<ul class="buttons">
								<li>
									<button type="submit" class="unpost" name="admin" value="unevaluate" id="unevaluateButton">
										<img src="<?+Visualizer::actionHref("style", "deleteButtonIcon.png") ?>" alt="" />選択した評価を削除
									</button>
								</li>
								<li>
									<button type="submit" class="unpost" name="admin" value="uncomment" id="uncommentButton">
										<img src="<?+Visualizer::actionHref("style", "deleteButtonIcon.png") ?>" alt="" />選択したコメントを削除
									</button>
								</li>
							</ul>
						</section>
					</form>
				<?endif ?>
			<?endif ?>
		<?else: ?>
			<section>
				<p class="commentIsOnLastPage">
					コメントは最後のページに表示されます。
				</p>
			</section>
		<?endif ?>
	<?endif ?>
	<? Visualizer::footer($h->thread->background) ?>
</body>
</html>