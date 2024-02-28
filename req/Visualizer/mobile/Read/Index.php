<?php
namespace Megalopolis;

$c = &Configuration::$instance;
$h = &ReadHandler::$instance;
$d = &Visualizer::$data;

if (!isset($h->entry) || !isset($h->thread)) throw new ApplicationException("Thread not found.");

$basePath = Visualizer::absoluteHref($h->subject, $h->entry->id);
$m = Util::isLength(App::$pathInfo[$last = count(App::$pathInfo) - 1], 1) ? Util::escapeInput(App::$pathInfo[$last]) : "h";
$isVertical = Cookie::getCookie(Cookie::MOBILE_VERTICAL_KEY, "yes") == "yes";

function makeMenu(string $basePath, string $current): void
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
			<a href="<?=Visualizer::escapeOutput(Visualizer::absoluteHref(ReadHandler::$instance->entry->subject, ReadHandler::$instance->entry->id, $k)) ?>" data-transition="none"<?php if ($k == $current): ?> class="ui-btn-active"<?php endif ?> data-icon="<?=$i ?>"><?=Visualizer::escapeOutput($n) ?></a>
		</li>
	<?php
	}
	
	echo '</ul>';
}

Visualizer::doctype();
?>
<html lang="ja">
<head>
	<?php Visualizer::head() ?>
	<title>
		<?=Visualizer::escapeOutput($h->entry->title) ?>
	</title>
	<script src="<?=Visualizer::escapeOutput(Visualizer::actionHref("script", "mobile", "Index", "Index.js")) ?>"></script>
	<script src="<?=Visualizer::escapeOutput(Visualizer::actionHref("script", "mobile", "Read", "Index.js")) ?>"></script>
	<?php if (!$isVertical): ?>
		<style>
			body.ui-mobile-viewport, div.ui-mobile-viewport, .ui-content
			{
				overflow-x: auto;
			}
			
			.read .content
			{
				<?php if (!Util::isEmpty($h->thread->foreground)): ?>
					color: <?=Visualizer::escapeOutput($h->thread->foreground) ?>;
				<?php endif ?>
				<?php if (!Util::isEmpty($h->thread->background)): ?>
					background-color: <?=Visualizer::escapeOutput($h->thread->background) ?>;
				<?php endif ?>
				<?php if (!Util::isEmpty($h->thread->backgroundImage)): ?>
					background-image: url('<?=Visualizer::escapeOutput((strpos($h->thread->backgroundImage, "http://") === 0 ? null : Visualizer::$basePath) . $h->thread->backgroundImage) ?>');
				<?php endif ?>
			}
		</style>
	<?php endif ?>
</head>
<body>
	<?php if ($m == "h"): ?>
		<div id="rhome" data-role="page" data-fullscreen="true" class="read">
			<header data-role="header" data-position="fixed" data-backbtn="false" data-theme="b">
				<h1><?=Visualizer::escapeOutput($h->entry->title) ?></h1>
				<a href="<?=Visualizer::escapeOutput(Visualizer::absoluteHref($h->entry->subject)) ?>" data-direction="reverse">戻る</a>
			</header>
			<div data-role="content" data-theme="e">
				<div class="content <?=$isVertical ? "vertical" : "horizontal" ?> id<?=Visualizer::escapeOutput($h->entry->id) ?>">
					<article class="contentWrapper">
						<?php if ($c->showHeaderInsideBorder): ?>
							<header>
								<h1><?=Visualizer::escapeOutput($h->entry->title) ?></h1>
								<?php if ($c->showName[Configuration::ON_ENTRY]): ?>
									<address>
										<?php Visualizer::convertedName($h->entry->name) ?>
									</address>
								<?php endif ?>
							</header>
						<?php endif ?>
						<?php if ($c->useSummary && $c->showSummary[Configuration::ON_ENTRY] && !Util::isEmpty($h->entry->summary)): ?>
							<p class="summary">
								<?php Visualizer::convertedSummary($h->entry->summary) ?>
							</p>
						<?php endif ?>
						<div>
							<?php Visualizer::convertedBody($h->thread, null, null, null, $isVertical ? array("br", "p", "a", "span", "font") : null) ?>
						</div>
						<footer>
							<?php Visualizer::convertedAfterword($h->thread, $isVertical ? array("br", "p", "a", "span", "font") : null) ?>
							<?php if ($c->showName[Configuration::ON_ENTRY]): ?>
								<address>
									<?php Visualizer::convertedName($h->entry->name) ?>
								</address>
							<?php endif ?>
						</footer>
					</article>
				</div>
			</div>
			<footer data-role="footer" data-position="fixed">
				<div data-role="navbar">
					<?php makeMenu($basePath, "h") ?>
				</div>
			</footer>
		</div>
	<?php elseif ($m == "c"): ?>
		<div id="rcomments" data-role="page" class="read fulllist">
			<header data-role="header" data-backbtn="false">
				<h1>コメント</h1>
				<?php if ($c->useComments): ?>
					<a href="<?=Visualizer::escapeOutput(Visualizer::absoluteHref($h->entry->subject, $h->entry->id, "p")) ?>" data-icon="plus" data-iconpos="notext" data-transition="slideup" class="ui-btn-right">評価する</a>
				<?php endif ?>
			</header>
			<div data-role="content">
				<ul data-role="listview">
					<?php if ($c->showComment[Configuration::ON_ENTRY]): ?>
						<?php foreach ($h->thread->comments as $i): ?>
							<li>
								<?php if ($c->showName[Configuration::ON_COMMENT]): ?>
									<?=Visualizer::escapeOutput($i->name) ?>
								<?php endif ?>
								<p class="ui-li-aside">
									<date><?=Visualizer::escapeOutput(Visualizer::formatDateTime($i->dateTime)) ?></date>
									<?php if ($i->evaluation && $c->showPoint[Configuration::ON_COMMENT]): ?>
										<br /><?=Visualizer::escapeOutput($i->evaluation->point) ?>
									<?php endif ?>
								</p>
								<p><?php Visualizer::convertedSummary($i->body) ?></p>
							</li>
						<?php endforeach ?>
					<?php endif ?>
				</ul>
			</div>
			<footer data-role="footer" data-position="fixed">
				<div data-role="navbar">
					<?php makeMenu($basePath, "c") ?>
				</div>
			</footer>
		</div>
	<?php elseif ($m == "p"): ?>
		<form id="rpost" data-role="page" class="read fulllist" action="<?=Visualizer::escapeOutput(Util::withMobileUniqueIDRequestSuffix(Visualizer::absoluteHref($h->subject, $h->entry->id, "comment"))) ?>" action="post">
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
						<label for="nameTextBox">名前: </label><input type="text" name="name" id="nameTextBox" value="<?=Visualizer::escapeOutput(Cookie::getCookie(Cookie::NAME_KEY)) ?>" />
					</li>
					<li>
						<label for="mailTextBox">メール: </label><input type="text" name="mail" id="mailTextBox" value="<?=Visualizer::escapeOutput(Cookie::getCookie(Cookie::MAIL_KEY)) ?>" />
					</li>
					<li>
						<label for="passwordTextBox">削除キー: </label><input type="text" name="password" id="passwordTextBox" value="<?=Visualizer::escapeOutput(Cookie::getCookie(Cookie::PASSWORD_KEY)) ?>" />
					</li>
					<?php if (!Util::isEmpty($c->postPassword)): ?>
						<li>
							<label for="postPasswordTextBox">投稿キー: </label><input type="text" name="postPassword" id="postPasswordTextBox" />
						</li>
					<?php endif ?>
					<?php if ($c->useCommentPoints()): ?>
						<li>
							<label for="pointComboBox">評価: </label>
							<select name="point" id="pointComboBox">
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
						</li>
					<?php endif ?>
					<li>
						<textarea name="body"></textarea>
					</li>
				</ul>
			</div>
		</form>
	<?php else: ?>
		<div id="rinfo" data-role="page" class="read">
			<header data-role="header" data-backbtn="false">
				<h1>情報</h1>
			</header>
			<div data-role="content">
				<h2>
					<?=Visualizer::escapeOutput($h->entry->title) ?>
				</h2>
				<ul data-role="listview" data-inset="true" class="deflist">
					<?php if ($c->showName[Configuration::ON_ENTRY]): ?>
						<li>
							<h3>名前</h3>
							<?php Visualizer::linkedName($h->entry->name) ?>
						</li>
					<?php endif ?>
					<li>
						<h3>投稿日時</h3>
						<?=Visualizer::escapeOutput(Visualizer::formatDateTime($h->entry->dateTime)) ?>
					</li>
					<li>
						<h3>更新日時</h3>
						<?=Visualizer::escapeOutput(Visualizer::formatDateTime($h->entry->getLatestLastUpdate())) ?>
					</li>
					<?php if ($c->showPoint[Configuration::ON_ENTRY]): ?>
						<li>
							<h3>POINT</h3>
							<?=Visualizer::escapeOutput($h->entry->points) ?>
						</li>
					<?php endif ?>
					<?php if ($c->showRate[Configuration::ON_ENTRY]): ?>
						<li>
							<h3>Rate</h3>
							<?=Visualizer::escapeOutput($h->entry->rate) ?>
						</li>
					<?php endif ?>
					<?php if ($c->showSize[Configuration::ON_ENTRY]): ?>
						<li>
							<h3>サイズ</h3>
							<?=Visualizer::escapeOutput($h->entry->size) ?>KB
						</li>
					<?php endif ?>
				</ul>
				<?php if ($h->entry->tags && $c->showTags[Configuration::ON_ENTRY]): ?>
					<h2>タグ</h2>
					<ul data-role="listview" data-inset="true">
						<?php foreach ($h->entry->tags as $i): ?>
							<li>
								<?php Visualizer::linkedTag($i) ?>
							</li>
						<?php endforeach ?>
					</ul>
				<?php endif ?>
			</div>
			<footer data-role="footer" data-position="fixed">
				<div data-role="navbar">
					<?php makeMenu($basePath, "i") ?>
				</div>
			</footer>
		</div>
	<?php endif ?>
</body>
</html>
