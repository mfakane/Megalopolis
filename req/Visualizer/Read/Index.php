<?php
$c = &Configuration::$instance;
$h = &ReadHandler::$instance;
$d = &Visualizer::$data;
$isEdit = in_array(App::$actionName, array("new", "edit"));
$isAdmin = Auth::hasSession(true);
Visualizer::doctype();
?>
<html>
<head>
	<? Visualizer::head() ?>
	<meta name="author" content="<?+$h->entry->name ?>" />
	<meta name="description" content="<?+$h->entry->summary ?>" />
	<meta name="keywords" content="<?+implode(",", $h->entry->tags) ?>" />
	<title>
		<?+$h->entry->title ?>
		-
		<?+$c->title ?>
	</title>
	<script src="<?+Visualizer::actionHref("script", "taketori", "taketori.js") ?>"></script>
	<script src="<?+Visualizer::actionHref("script", "Read", "Index.js") ?>"></script>
	<script id="commentTemplate" type="text/x-jquery-tmpl">
		<dt id="comment${num}">
			${num}.
			<?if ($isAdmin || $c->showPoint[Configuration::ON_COMMENT]): ?>
				{{if !evaluation}}
					<span class="point none">無評価</span>
				{{else evaluation > 0}}
					<span class="point plus">${evaluation}</span>点
				{{else evaluation < 0}}
					<span class="point minus">${evaluation}</span>点
				{{/if}}
			<?endif ?>
			<?if ($isAdmin || $c->showName[Configuration::ON_COMMENT]): ?>
				<span class="name">
					{{if mail}}
						<a href="mailto:${mail}">
							${name}
						</a>
					{{else}}
						${name}
					{{/if}}
				</span>
			<?endif ?>
			<time datetime="${megalopolis.unixTimeAsString(dateTime, 0)}">
				${megalopolis.unixTimeAsString(dateTime, 1)}
			</time>
			<a href="${deleteAction}">削除</a>
		</dt>
		<dd>
			{{html formattedBody}}
		</dd>
	</script>
</head>
<body class="read">
	<? Visualizer::header($h->entry->title, App::$actionName == "index"
		? array
		(
			"{$h->subject}, 作品集に戻る, returnIcon.png",
			"{$h->subject}/{$h->entry->id}/edit, 編集, editIcon.png"
		)
		: array()) ?>
	<dl class="status">
		<dd>
			<?+Visualizer::formatDateTime($h->entry->dateTime) ?>
		</dd>
		<dt>最終更新</dt>
		<dd>
			<time pubdate="pubdate" datetime="<?+date("c", $h->entry->lastUpdate) ?>">
				<?+Visualizer::formatDateTime($h->entry->lastUpdate) ?>
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
		  $c->useAnyPoints() && ($c->showPoint[Configuration::ON_ENTRY] || $c->showRate[Configuration::ON_ENTRY])): ?>
		<dl class="status">
			<?if ($c->showReadCount[Configuration::ON_ENTRY]): ?>
				<dt>閲覧数</dt>
				<dd>
					<?+$h->entry->readCount ?>
				</dd>
			<?endif ?>
			<?if ($c->useAnyPoints()): ?>
				<?if ($c->showPoint[Configuration::ON_ENTRY]): ?>
					<dt>評価数</dt>
					<dd id="evaluationCount">
						<?+$h->entry->evaluationCount ?>
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
						<a href="<?+Visualizer::actionHref("tag", $i) ?>"><?+$i ?></a>
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
	<? Visualizer::pager($h->page, $h->entry->pageCount, 5, App::$actionName != "index"
		? (App::$actionName == "new" ? Visualizer::actionHref(App::$actionName) . "/" : Visualizer::actionHref($h->subject, $h->entry->id, App::$actionName, array("p" => "")))
		: Visualizer::actionHref($h->subject, $h->entry->id) . "/") ?>
	<section id="body" style="color: <?+$h->thread->foreground ?>; background-color: <?+$h->thread->background ?>; background-image: <?+!Util::isEmpty($h->thread->backgroundImage) ? "url('{$h->thread->backgroundImage}')" : "none" ?>;">
		<div id="contentWrapper">
			<?if ($h->page == 1): ?>
				<h1>
					<?+$h->entry->title ?>
				</h1>
				<?if ($c->showName[Configuration::ON_ENTRY]): ?>
					<address>
						<?if (App::$actionName == "index"): ?>
							<a href="<?+Visualizer::actionHref("author", $h->entry->name) ?>"><? Visualizer::convertedName($h->entry->name) ?></a>
						<?else: ?>
							<? Visualizer::convertedName($h->entry->name) ?>
						<?endif ?>
					</address>
				<?endif ?>
				<?if (!Util::isEmpty($h->entry->summary) && $c->showSummary[Configuration::ON_ENTRY]): ?>
					<p id="summary">
						<? Visualizer::convertedSummary($h->entry->summary) ?>
					</p>
				<?endif ?>
			<?endif ?>
			<div id="content">
				<script>
					megalopolis.read.loadOptions('<?+Visualizer::$basePath ?>');
				</script>
				<? Visualizer::convertedBody($h->thread, $h->page) ?>
			</div>
			<?if ($h->page == $h->entry->pageCount): ?>
				<div id="afterword">
					<? Visualizer::convertedAfterword($h->thread) ?>
					<?if ($c->showName[Configuration::ON_ENTRY]): ?>
						<address>
							<?if (App::$actionName == "index"): ?>
								<a href="<?+Visualizer::actionHref("author", $h->entry->name) ?>"><? Visualizer::convertedName($h->entry->name) ?></a>
							<?else: ?>
								<? Visualizer::convertedName($h->entry->name) ?>
							<?endif ?>
							<?if (!Util::isEmpty($h->entry->mail) || !Util::isEmpty($h->entry->link)): ?>
								<br />
							<?endif ?>
							<?if (!Util::isEmpty($h->entry->mail)): ?>
								<a href="mailto:<?+$h->entry->mail ?>">[メール]</a>
							<?endif ?>
							<?if (!Util::isEmpty($h->entry->link)): ?>
								<a href="<?+$h->entry->link ?>">[リンク]</a>
							<?endif ?>
						</address>
					<?endif ?>
				</div>
			<?endif ?>
			<?if (App::$actionName == "index" && $c->showTweetButton): ?>
				<footer>
					<a href="http://twitter.com/share?url=<?+Visualizer::absoluteHref($h->subject, $h->entry->id) ?>" class="twitter-share-button" data-count="horizontal" data-lang="ja">Tweet</a>
					<script type="text/javascript" src="http://platform.twitter.com/widgets.js"></script>
				</footer>
			<?endif ?>
		</div>
	</section>
	<? Visualizer::pager($h->page, $h->entry->pageCount, 5, App::$actionName != "index"
		? (App::$actionName == "new" ? Visualizer::actionHref(App::$actionName) . "/" : Visualizer::actionHref($h->subject, $h->entry->id, App::$actionName, array("p" => "")))
		: Visualizer::actionHref($h->subject, $h->entry->id) . "/") ?>
	<?if ($isEdit): ?>
		<section class="notify info">
			<ul class="buttons">
				<li>
					<form action="<? Visualizer::converted(App::$actionName == "new"
						? Visualizer::actionHref(App::$actionName)
						: Visualizer::actionHref($h->subject, $h->entry->id, App::$actionName)) ?>" method="post">
						<div>
							<button type="submit">
								<img src="<?+Visualizer::actionHref("style", "backButtonIcon.png") ?>" />修正
							</button>
							<? ReadHandler::printHiddenParams() ?>
						</div>
					</form>
				</li>
				<li>
					<form action="<? Visualizer::converted(App::$actionName == "new"
						? Visualizer::actionHref("post")
						: Visualizer::actionHref($h->subject, $h->entry->id, "post")) ?>" method="post">
						<div>
							<button type="submit">
								送信<img src="<?+Visualizer::actionHref("style", "sendButtonIcon.png") ?>" class="last" />
							</button>
							<? ReadHandler::printHiddenParams() ?>
						</div>
					</form>
				</li>
			</ul>
			間違いが無ければ [送信] をクリックし投稿します。修正すべき箇所がある場合は [修正] をクリックし編集画面に戻ります
		</section>
	<? else: ?>
		<div id="links"></div>
		<?if ($c->usePoints()): ?>
			<a id="evaluateformHeadding" href="#evaluateform" class="first">簡易評価</a>
			<form id="evaluateform" action="<?+Visualizer::actionHref($h->subject, $h->entry->id, "evaluate") ?>#evaluateformHeadding" method="post">
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
			<form id="commentform" action="<?+Visualizer::actionHref($h->subject, $h->entry->id, "comment") ?>#commentformHeadding" method="post">
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
							<img src="<?+Visualizer::actionHref("style", "writeButtonIcon.png") ?>" />送信
						</button>
					</li>
				</ul>
			</form>
		<?endif ?>
		<?if ($c->usePoints() && $c->useComments): ?>
			<script>
				megalopolis.read.loadForms();
			</script>
		<?endif ?>
		<?if ($c->showComment[Configuration::ON_ENTRY]): ?>
			<?if ($isAdmin): ?>
				<form action="" method="post">
			<?endif ?>
			<dl id="comments">
				<?if (($h->thread->nonCommentEvaluations || $c->pointMap) && $c->showPoint[Configuration::ON_COMMENT]): ?>
					<?if ($isAdmin): ?>
						<?if ($h->thread->nonCommentEvaluations): ?>
							<? $count = 0 ?>
							<?foreach ($h->thread->nonCommentEvaluations as $i): ?>
								<dt class="evaluation">
									<input type="checkbox" name="id[]" value="<?+$i->id ?>" />
									<?+(++$count) ?>.
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
					<? $count = 0 ?>
					<?foreach ($h->thread->comments as $i): ?>
						<dt id="comment<?+(++$count) ?>">
							<?if ($isAdmin): ?>
								<input type="checkbox" name="id[]" value="<?+$i->id ?>" />
							<?endif ?>
							<?+$count ?>.
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
											<?+$i->name ?>
										</a>
									<?else: ?>
										<?+$i->name ?>
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
					<input type="hidden" name="sessionID" value="<?+Auth::getSessionID() ?>" />
					<section class="admin">
						<ul class="buttons">
							<li>
								<button type="submit" class="unpost" name="admin" value="unevaluate" id="unevaluateButton">
									<img src="<?+Visualizer::actionHref("style", "deleteButtonIcon.png") ?>" />選択した評価を削除
								</button>
							</li>
							<li>
								<button type="submit" class="unpost" name="admin" value="uncomment" id="uncommentButton">
									<img src="<?+Visualizer::actionHref("style", "deleteButtonIcon.png") ?>" />選択したコメントを削除
								</button>
							</li>
						</ul>
					</section>
				</form>
			<?endif ?>
		<?endif ?>
	<?endif ?>
	<? Visualizer::footer() ?>
</body>
</html>