<?php
function entries($entries, $isAdmin, $listType = null, $noSingle = false)
{
	$c = Configuration::$instance;
	
	if (!$listType)
		switch ($c->listType)
		{
			case Configuration::LIST_DOUBLE:
				$listType = "double";
				
				break;
			case Configuration::LIST_SINGLE:
				$listType = "single";
				
				break;
		}
	
	$listType = Cookie::getCookie(Cookie::LIST_TYPE_KEY, $listType);
	
	if ($noSingle &&
		$listType == "single")
		$listType = "double";
	
	?>
	<div class="entries<?php echo $listType == "single" ? " single" : null ?>">
		<?php foreach ($entries as $idx => $i): ?><article>
				<?php if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
					<h2>
						<?php if ($isAdmin): ?>
							<label>
								<input type="checkbox" name="id[]" value="<?php Visualizer::converted($i->id) ?>" />
						<?php endif ?>
						<a href="<?php Visualizer::converted(Visualizer::actionHref($i->subject, $i->id)) ?>"><?php Visualizer::converted($i->title) ?></a>
						<?php if ($isAdmin): ?>
							</label>
						<?php endif ?>
					</h2>
				<?php endif ?>
				<time pubdate="pubdate" datetime="<?php Visualizer::converted(date("c", $i->dateTime)) ?>">
					<?php Visualizer::converted(Visualizer::formatDateTime($i->dateTime)) ?>
				</time>
				<?php if ($c->showName[Configuration::ON_SUBJECT]): ?>
					<a href="<?php Visualizer::converted(Visualizer::actionHref("author", $i->name)) ?>" class="name"><?php Visualizer::convertedName($i->name) ?></a>
				<?php endif ?>
				<?php if ($c->showPages[Configuration::ON_SUBJECT] ||
					  $c->showSize[Configuration::ON_SUBJECT] ||
					  $c->showReadCount[Configuration::ON_SUBJECT] ||
					  $c->useAnyPoints() && ($c->showPoint[Configuration::ON_SUBJECT] || $c->showRate[Configuration::ON_SUBJECT])): ?>
				<dl>
					<?php if ($c->showPages[Configuration::ON_SUBJECT]): ?>
						<dt>ページ数</dt>
						<dd class="pageCount">
							<?php Visualizer::converted($i->pageCount) ?>
						</dd>
					<?php endif ?>
					<?php if ($c->showSize[Configuration::ON_SUBJECT]): ?>
						<dt>サイズ</dt>
						<dd>
							<span class="size"><?php Visualizer::converted($i->size) ?></span>KB
						</dd>
					<?php endif ?>
					<?php if ($c->showReadCount[Configuration::ON_SUBJECT]): ?>
						<dt>閲覧数</dt>
						<dd class="readCount">
							<?php Visualizer::converted($i->readCount) ?>
						</dd>
					<?php endif ?>
					<?php if ($c->useAnyPoints()): ?>
						<?php if ($c->showPoint[Configuration::ON_SUBJECT]): ?>
							<dt>評価数</dt>
							<dd class="evaluationCount">
								<?php Visualizer::converted($i->evaluationCount) ?>
							</dd>
							<dt>POINT</dt>
							<dd class="points">
								<?php Visualizer::converted($i->points) ?>
							</dd>
						<?php endif ?>
						<?php if ($c->showRate[Configuration::ON_SUBJECT]): ?>
							<dt>Rate</dt>
							<dd class="rate">
								<?php Visualizer::converted(sprintf("%.2f", $i->rate)) ?>
							</dd>
						<?php endif ?>
					<?php endif ?>
				</dl>
				<?php endif ?>
				<?php if ($i->tags && $c->showTags[Configuration::ON_SUBJECT]): ?>
					<ul class="tags">
						<?foreach ($i->tags as $j): ?>
							<li>
								<a href="<?php Visualizer::converted(Visualizer::actionHref("tag", $j)) ?>"><?php Visualizer::converted($j) ?></a>
							</li>
						<?php endforeach ?>
					</ul>
				<?php endif ?>
				<?php if (!Util::isEmpty($i->summary) && $c->useSummary && $c->showSummary[Configuration::ON_SUBJECT]): ?>
					<p>
						<?php Visualizer::convertedSummary($i->summary) ?>
					</p>
				<?php endif ?>
			</article><?php endforeach ?>
	</div>
	<?php
}
?>