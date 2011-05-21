<?php
$c = &Configuration::$instance;
$h = &ReadHandler::$instance;

$title = App::$actionName == "new" ? "新規投稿" : "{$h->entry->title} の編集";
Visualizer::doctype();
?>
<html>
<head>
	<? Visualizer::head() ?>
	<title>
		<?+$title ?>
		-
		<?+$c->title ?>
	</title>
	<?if ($c->foregroundMap || $c->backgroundMap || $c->backgroundImageMap): ?>
		<script src="<?+Visualizer::actionHref("script", "Read", "Edit.js") ?>"></script>
	<?endif ?>
</head>
<body class="edit">
	<? Visualizer::header($title, App::$actionName == "new"
		? array()
		: array
	(
		"{$h->subject}/{$h->entry->id}, 作品に戻る, returnIcon.png"
	)) ?>
	<?if (Visualizer::$data): ?>
		<ul class="notify warning">
			<?foreach (Visualizer::$data as $i): ?>
				<li>
					<?+$i ?>
				</li>
			<?endforeach ?>
		</ul>
	<?endif ?>
	<form action="" method="post">
		<section>
			<label for="name">名前</label><input type="text" name="name" id="name" value="<?+$h->entry->name ?>"<?=$c->requireName[Configuration::ON_ENTRY] ? 'required="required"' : null ?> /><br />
			<label for="mail">メール</label><input type="email" name="mail" id="mail" value="<?+$h->entry->mail ?>" /><br />
			<label for="link">リンク</label><input type="url" name="link" id="link" value="<?+$h->entry->link ?>" /><br />
			<label for="password">編集キー</label><input type="password" name="password" id="password" value="<?+App::$actionName == "new" ? Cookie::getCookie(Cookie::PASSWORD_KEY) : null ?>"<?=$c->requirePassword[Configuration::ON_ENTRY] ? ' required="required"' : null ?> />
			<?if (App::$actionName == "edit"): ?>
				<p>空欄にしておくと、編集キーを変更しません。</p>
			<?endif ?>
			<hr />
			<?if ($c->foregroundEnabled): ?>
				<label for="foreground">文字色</label><input type="color" name="foreground" id="foreground" onchange="updateForeground()" value="<?+$h->thread->foreground ?>" />
				<?if ($c->foregroundMap): ?>
					<script>
						megalopolis.edit.foregroundPalette([<?=implode(", ", array_map(create_function('$_', 'return "\'" . Visualizer::escapeOutput($_) . "\'";'), $c->foregroundMap)) ?>]);
					</script>
				<?else: ?>
					<br />
				<?endif ?>
			<?endif ?>
			<?if ($c->backgroundEnabled): ?>
				<label for="background">背景色</label><input type="color" name="background" id="background" onchange="updateBackground()" value="<?+$h->thread->background ?>" />
				<?if ($c->backgroundMap): ?>
					<script>
						megalopolis.edit.backgroundPalette([<?=implode(", ", array_map(create_function('$_', 'return "\'" . Visualizer::escapeOutput($_) . "\'";'), $c->backgroundMap)) ?>]);
					</script>
				<?else: ?>
					<br />
				<?endif ?>
			<?endif ?>
			<?if ($c->backgroundImageEnabled): ?>
				<label for="backgroundImage">背景画像</label><input type="url" name="backgroundImage" id="backgroundImage" onchange="updateBackgroundImage()" value="<?+$h->thread->backgroundImage ?>" />
				<?if ($c->backgroundImageMap): ?>
					<script>
						megalopolis.edit.backgroundImagePalette([<?=implode(", ", array_map(create_function('$_', 'return "\'" . Visualizer::escapeOutput($_) . "\'";'), $c->backgroundImageMap)) ?>]);
					</script>
				<?else: ?>
					<br />
				<?endif ?>
			<?endif ?>
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
			<label for="summary">概要</label>
			<textarea name="summary" id="summary" rows="2" cols="60"><?+$h->entry->summary ?></textarea>
			<hr />
			<label for="body">本文</label><textarea name="body" id="body" rows="20" cols="60"><?+$h->thread->body ?></textarea><br />
			<label for="afterword">あとがき</label><textarea name="afterword" id="afterword" rows="4" cols="60"><?+$h->thread->afterword ?></textarea>
			<ul class="buttons">
				<li>
					<button type="submit">
						<img src="<?+Visualizer::actionHref("style", "sendButtonIcon.png") ?>" />確認
					</button>
					<input type="hidden" name="preview" value="true" />
				</li>
			</ul>
		</section>
	</form>
	<?if (App::$actionName == "edit"): ?>
		<form action="unpost" method="post" id="unpostForm">
			<section>
				<h2>作品の削除</h2>
				<label><input type="checkbox" name="sessionID" id="unpostCheck" value="<?+Auth::getSessionID() ?>" />作品を削除する</label>
				<ul class="buttons">
					<li>
						<button type="submit" id="unpostSubmit">
							<img src="<?+Visualizer::actionHref("style", "deleteButtonIcon.png") ?>" />削除
						</button>
					</li>
				</ul>
			</section>
		</form>
	<?endif ?>
	<? Visualizer::footer() ?>
</body>
</html>