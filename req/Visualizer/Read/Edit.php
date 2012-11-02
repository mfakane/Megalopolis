<?php
$c = &Configuration::$instance;
$h = &ReadHandler::$instance;

$title = App::$actionName == "new" ? "新規投稿" : "{$h->entry->title} の編集";
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<? Visualizer::head() ?>
	<title>
		<?+$title ?>
		-
		<?+$c->title ?>
	</title>
	<?if ($c->foregroundMap || $c->backgroundMap || $c->backgroundImageMap || $c->borderMap): ?>
		<script src="<?+Visualizer::actionHref("script", "Read", "Edit.js") ?>"></script>
	<?endif ?>
</head>
<body class="edit">
	<? Visualizer::header($title, App::$actionName == "new"
		? array()
		: array
		(
			"{$h->subject}/{$h->entry->id}" => array("作品に戻る", "returnIcon.png")
		))
	?>
	<?if (in_array(Util::getBrowserType(), array
	(
		Util::BROWSER_TYPE_MSIE6,
		Util::BROWSER_TYPE_MSIE7,
		Util::BROWSER_TYPE_FIREFOX2,
	))): ?>
		<p class="notify warning">
			古いブラウザを使っているため、表示が乱れたり、ページの一部が使用不能になる可能性があります。最新のバージョンへ変更することを推奨します。
		</p>
	<?endif ?>
	<?if (Visualizer::$data): ?>
		<ul class="notify warning">
			<?foreach (Visualizer::$data as $i): ?>
				<li>
					<?+$i ?>
				</li>
			<?endforeach ?>
		</ul>
	<?endif ?>
	<form action="<?+Util::withMobileUniqueIDRequestSuffix() ?>" method="post">
		<section>
			<label for="name">名前</label><input type="text" name="name" id="name" value="<?+$h->entry->name ?>"<?=$c->requireName[Configuration::ON_ENTRY] ? 'required="required"' : null ?> /><br />
			<label for="mail">メール</label><input type="email" name="mail" id="mail" value="<?+$h->entry->mail ?>" /><br />
			<label for="link">リンク</label><input type="url" name="link" id="link" value="<?+$h->entry->link ?>" /><br />
			<label for="editPassword">編集キー</label><input type="password" name="editPassword" id="editPassword" value="<?+App::$actionName == "new" ? Cookie::getCookie(Cookie::PASSWORD_KEY) : null ?>"<?=App::$actionName == "new" && $c->requirePassword[Configuration::ON_ENTRY] ? ' required="required"' : null ?> />
			<?if (App::$actionName == "edit"): ?>
				<p>空欄にしておくと、編集キーを変更しません。</p>
			<?endif ?>
			<hr />
			<?if ($c->foregroundEnabled): ?>
				<label for="foreground">文字色</label>
				<input type="color" name="foreground" id="foreground" value="<?+$h->thread->foreground ?>"<?if ($c->foregroundMap) echo ' data-map="' . implode(" ", array_map(array("Visualizer", "escapeOutput"), $c->foregroundMap)) . '"'; ?> />
				<?if (!$c->foregroundMap): ?>
					<br />
				<?endif ?>
			<?endif ?>
			<?if ($c->backgroundEnabled): ?>
				<label for="background">背景色</label>
				<input type="color" name="background" id="background" value="<?+$h->thread->background ?>"<?if ($c->backgroundMap) echo ' data-map="' . implode(" ", array_map(array("Visualizer", "escapeOutput"), $c->backgroundMap)) . '"'; ?> />
				<?if (!$c->backgroundMap): ?>
					<br />
				<?endif ?>
			<?endif ?>
			<?if ($c->backgroundImageEnabled): ?>
				<label for="backgroundImage">背景画像</label>
				<input type="text" name="backgroundImage" id="backgroundImage" value="<?+$h->thread->backgroundImage ?>"<?if ($c->backgroundImageMap) echo ' data-map="' . implode(" ", array_map(array("Visualizer", "escapeOutput"), $c->backgroundImageMap)) . '"'; ?> />
				<?if (!$c->backgroundImageMap): ?>
					<br />
				<?endif ?>
			<?endif ?>
			<?if ($c->borderEnabled): ?>
				<label for="border">枠色</label>
				<input type="color" name="border" id="border" value="<?+$h->thread->border ?>"<?if ($c->borderMap) echo ' data-map="' . implode(" ", array_map(array("Visualizer", "escapeOutput"), $c->borderMap)) . '"'; ?> />
				<?if (!$c->borderMap): ?>
					<br />
				<?endif ?>
			<?endif ?>
			<?if ($c->foregroundEnabled || $c->backgroundEnabled || $c->backgroundImageEnabled || $c->borderEnabled): ?>
				<hr />
			<?endif ?>
			<label for="writingMode">既定の方向</label>
			<ul>
				<li>
					<label><input type="radio" id="writingMode" name="writingMode" value="<?+Thread::WRITING_MODE_NOT_SPECIFIED ?>"<?if ($h->thread->writingMode == Thread::WRITING_MODE_NOT_SPECIFIED): ?> checked="checked"<?endif ?> />指定しない</label>
				</li>
				<li>
					<label><input type="radio" name="writingMode" value="<?+Thread::WRITING_MODE_HORIZONTAL ?>"<?if ($h->thread->writingMode == Thread::WRITING_MODE_HORIZONTAL): ?> checked="checked"<?endif ?> />横書き</label>
				</li>
				<li>
					<label><input type="radio" name="writingMode" value="<?+Thread::WRITING_MODE_VERTICAL ?>"<?if ($h->thread->writingMode == Thread::WRITING_MODE_VERTICAL): ?> checked="checked"<?endif ?> />縦書き</label>
				</li>
			</ul>
			<ul>
				<li>
					<input type="hidden" name="convertLineBreak" value="false" />
					<label><input type="checkbox" name="convertLineBreak" value="true"<?if ($h->thread->convertLineBreak): ?> checked="checked"<?endif ?> />改行を HTML タグに自動変換する</label>
				</li>
			</ul>
			<?if (!Util::isEmpty($c->postPassword)): ?>
				<hr />
				<label for="postPassword">投稿キー</label><input type="password" name="postPassword" id="postPassword" required="required" />
			<?endif ?>
			<div class="notice">
				本文とあとがきには HTML タグが使用可能です。<br />
				&lt;split /&gt; タグを使うことにより、ページ分割が可能です。
			</div>
		</section>
		<section>
			<label for="title">作品名</label><input type="text" name="title" id="title" value="<?+$h->entry->title ?>" required="required" /><br />
			<?if ($c->maxTags): ?>
				<label for="tags">分類タグ</label><input type="text" name="tags" id="tags" value="<?+implode(" ", $h->entry->tags) ?>" /><br />
				<p>スペース区切りで <?+$c->maxTags ?> 個まで入力できます</p>
			<?endif ?>
			<?if ($c->useSummary): ?>
			<label for="summary">概要</label>
			<textarea name="summary" id="summary" rows="2" cols="60"><?+$h->entry->summary ?></textarea>
			<?endif ?>
			<hr />
			<label for="body">本文</label><textarea name="body" id="body" rows="20" cols="60"><?+$h->thread->body ?></textarea><br />
			<label for="afterword">あとがき</label><textarea name="afterword" id="afterword" rows="4" cols="60"><?+$h->thread->afterword ?></textarea>
			<ul class="buttons">
				<li>
					<button type="submit">
						<img src="<?+Visualizer::actionHref("style", "sendButtonIcon.png") ?>" alt="" />確認
					</button>
					<input type="hidden" name="password" value="<?+ReadHandler::param("password") ?>" />
					<input type="hidden" name="preview" value="true" />
				</li>
			</ul>
		</section>
	</form>
	<?if (App::$actionName == "edit"): ?>
		<form action="<?+Util::withMobileUniqueIDRequestSuffix("unpost") ?>" method="post" id="unpostForm">
			<section>
				<h2>作品の削除</h2>
				<label><input type="checkbox" name="password" id="unpostCheck" value="<?+ReadHandler::param("password") ?>" />作品を削除する</label>
				<ul class="buttons">
					<li>
						<button type="submit" id="unpostSubmit">
							<img src="<?+Visualizer::actionHref("style", "deleteButtonIcon.png") ?>" alt="" />削除
						</button>
					</li>
				</ul>
			</section>
		</form>
	<?endif ?>
	<? Visualizer::footer() ?>
</body>
</html>