<?php
namespace Megalopolis;

$c = &Configuration::$instance;
$h = &ReadHandler::$instance;

if (!isset($h->entry) || !isset($h->thread)) throw new ApplicationException("Thread not found.");

$title = App::$actionName == "new" ? "新規投稿" : "{$h->entry->title} の編集";
Visualizer::doctype();
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<title>
		<?=Visualizer::escapeOutput($title) ?>
		-
		<?=Visualizer::escapeOutput($c->title) ?>
	</title>
	<?php if ($c->foregroundMap || $c->backgroundMap || $c->backgroundImageMap || $c->borderMap): ?>
		<script src="<?=Visualizer::escapeOutput(Visualizer::actionHref("script", "Read", "Edit.js")) ?>"></script>
	<?php endif ?>
</head>
<body class="edit">
	<?php Visualizer::header($title, App::$actionName == "new"
		? array()
		: array
		(
			"{$h->subject}/{$h->entry->id}" => array("作品に戻る", "returnIcon.png")
		))
	?>
	<?php if (in_array(Util::getBrowserType(), array
	(
		Util::BROWSER_TYPE_MSIE6,
		Util::BROWSER_TYPE_MSIE7,
		Util::BROWSER_TYPE_FIREFOX2,
	))): ?>
		<p class="notify warning">
			古いブラウザを使っているため、表示が乱れたり、ページの一部が使用不能になる可能性があります。最新のバージョンへ変更することを推奨します。
		</p>
	<?php endif ?>
	<?php if (Visualizer::$data): ?>
		<ul class="notify warning">
			<?php foreach (Visualizer::$data as $i): ?>
				<li>
					<?=Visualizer::escapeOutput($i) ?>
				</li>
			<?php endforeach ?>
		</ul>
	<?php endif ?>
	<form action="<?=Visualizer::escapeOutput(Util::withMobileUniqueIDRequestSuffix()) ?>" method="post">
		<section>
			<label for="name">名前</label><input type="text" name="name" id="name" value="<?=Visualizer::escapeOutput($h->entry->name) ?>"<?=$c->requireName[Configuration::ON_ENTRY] ? 'required="required"' : null ?> /><br />
			<label for="mail">メール</label><input type="email" name="mail" id="mail" value="<?=Visualizer::escapeOutput($h->entry->mail) ?>" /><br />
			<label for="link">リンク</label><input type="url" name="link" id="link" value="<?=Visualizer::escapeOutput($h->entry->link) ?>" /><br />
			<label for="editPassword">編集キー</label><input type="password" name="editPassword" id="editPassword" value="<?=Visualizer::escapeOutput(App::$actionName == "new" ? Cookie::getCookie(Cookie::PASSWORD_KEY) : null) ?>"<?=App::$actionName == "new" && $c->requirePassword[Configuration::ON_ENTRY] ? ' required="required"' : null ?> />
			<?php if (App::$actionName == "edit"): ?>
				<p>空欄にしておくと、編集キーを変更しません。</p>
			<?php endif ?>
			<hr />
			<?php if ($c->foregroundEnabled): ?>
				<label for="foreground">文字色</label>
				<input type="color" name="foreground" id="foreground" value="<?=Visualizer::escapeOutput($h->thread->foreground) ?>"<?php if ($c->foregroundMap) echo ' data-map="' . implode(" ", array_map(fn($x) => Visualizer::escapeOutput($x), $c->foregroundMap)) . '"'; ?> />
				<?php if (!$c->foregroundMap): ?>
					<br />
				<?php endif ?>
			<?php endif ?>
			<?php if ($c->backgroundEnabled): ?>
				<label for="background">背景色</label>
				<input type="color" name="background" id="background" value="<?=Visualizer::escapeOutput($h->thread->background) ?>"<?php if ($c->backgroundMap) echo ' data-map="' . implode(" ", array_map(fn($x) => Visualizer::escapeOutput($x), $c->backgroundMap)) . '"'; ?> />
				<?php if (!$c->backgroundMap): ?>
					<br />
				<?php endif ?>
			<?php endif ?>
			<?php if ($c->backgroundImageEnabled): ?>
				<label for="backgroundImage">背景画像</label>
				<input type="text" name="backgroundImage" id="backgroundImage" value="<?=Visualizer::escapeOutput($h->thread->backgroundImage) ?>"<?php if ($c->backgroundImageMap) echo ' data-map="' . implode(" ", array_map(fn($x) => Visualizer::escapeOutput($x), $c->backgroundImageMap)) . '"'; ?> />
				<?php if (!$c->backgroundImageMap): ?>
					<br />
				<?php endif ?>
			<?php endif ?>
			<?php if ($c->borderEnabled): ?>
				<label for="border">枠色</label>
				<input type="color" name="border" id="border" value="<?=Visualizer::escapeOutput($h->thread->border) ?>"<?php if ($c->borderMap) echo ' data-map="' . implode(" ", array_map(fn($x) => Visualizer::escapeOutput($x), $c->borderMap)) . '"'; ?> />
				<?php if (!$c->borderMap): ?>
					<br />
				<?php endif ?>
			<?php endif ?>
			<?php if ($c->foregroundEnabled || $c->backgroundEnabled || $c->backgroundImageEnabled || $c->borderEnabled): ?>
				<hr />
			<?php endif ?>
			<label for="writingMode">既定の方向</label>
			<ul>
				<li>
					<label><input type="radio" id="writingMode" name="writingMode" value="<?=Visualizer::escapeOutput(Thread::WRITING_MODE_NOT_SPECIFIED) ?>"<?php if ($h->thread->writingMode == Thread::WRITING_MODE_NOT_SPECIFIED): ?> checked="checked"<?php endif ?> />指定しない</label>
				</li>
				<li>
					<label><input type="radio" name="writingMode" value="<?=Visualizer::escapeOutput(Thread::WRITING_MODE_HORIZONTAL) ?>"<?php if ($h->thread->writingMode == Thread::WRITING_MODE_HORIZONTAL): ?> checked="checked"<?php endif ?> />横書き</label>
				</li>
				<li>
					<label><input type="radio" name="writingMode" value="<?=Visualizer::escapeOutput(Thread::WRITING_MODE_VERTICAL) ?>"<?php if ($h->thread->writingMode == Thread::WRITING_MODE_VERTICAL): ?> checked="checked"<?php endif ?> />縦書き</label>
				</li>
			</ul>
			<ul>
				<li>
					<input type="hidden" name="convertLineBreak" value="false" />
					<label><input type="checkbox" name="convertLineBreak" value="true"<?php if ($h->thread->convertLineBreak): ?> checked="checked"<?php endif ?> />改行を HTML タグに自動変換する</label>
				</li>
			</ul>
			<?php if (!Util::isEmpty($c->postPassword)): ?>
				<hr />
				<label for="postPassword">投稿キー</label><input type="password" name="postPassword" id="postPassword" required="required" />
			<?php endif ?>
			<div class="notice">
				本文とあとがきには HTML タグが使用可能です。<br />
				&lt;split /&gt; タグを使うことにより、ページ分割が可能です。
			</div>
		</section>
		<section>
			<label for="title">作品名</label><input type="text" name="title" id="title" value="<?=Visualizer::escapeOutput($h->entry->title) ?>" required="required" /><br />
			<?php if ($c->maxTags): ?>
				<label for="tags">分類タグ</label><input type="text" name="tags" id="tags" value="<?=Visualizer::escapeOutput(implode(" ", $h->entry->tags)) ?>" /><br />
				<p>スペース区切りで <?=Visualizer::escapeOutput($c->maxTags) ?> 個まで入力できます</p>
			<?php endif ?>
			<?php if ($c->useSummary): ?>
			<label for="summary">概要</label>
			<textarea name="summary" id="summary" rows="2" cols="60"><?=Visualizer::escapeOutput($h->entry->summary) ?></textarea>
			<?php endif ?>
			<hr />
			<label for="body">本文</label><textarea name="body" id="body" rows="20" cols="60"><?=Visualizer::escapeOutput($h->thread->body) ?></textarea><br />
			<label for="afterword">あとがき</label><textarea name="afterword" id="afterword" rows="4" cols="60"><?=Visualizer::escapeOutput($h->thread->afterword) ?></textarea>
			<ul class="buttons">
				<li>
					<button type="submit">
						<img src="<?=Visualizer::escapeOutput(Visualizer::actionHref("style", "sendButtonIcon.png")) ?>" alt="" />確認
					</button>
					<input type="hidden" name="password" value="<?=Visualizer::escapeOutput(ReadHandler::param("password")) ?>" />
					<input type="hidden" name="preview" value="true" />
				</li>
			</ul>
		</section>
	</form>
	<?php if (App::$actionName == "edit"): ?>
		<form action="<?=Visualizer::escapeOutput(Util::withMobileUniqueIDRequestSuffix("unpost")) ?>" method="post" id="unpostForm">
			<section>
				<h2>作品の削除</h2>
				<label><input type="checkbox" name="password" id="unpostCheck" value="<?=Visualizer::escapeOutput(ReadHandler::param("password")) ?>" />作品を削除する</label>
				<ul class="buttons">
					<li>
						<button type="submit" id="unpostSubmit">
							<img src="<?=Visualizer::escapeOutput(Visualizer::actionHref("style", "deleteButtonIcon.png")) ?>" alt="" />削除
						</button>
					</li>
				</ul>
			</section>
		</form>
	<?php endif ?>
	<?php Visualizer::footer() ?>
</body>
</html>
