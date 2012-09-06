<?php
function entryInfo(&$idx, $i, $visibility, $label, $member, $value = null)
{
	?>
	<dt<?php echo in_array($member, $visibility) ? ($idx++ == 0 ? ' class="firstChild"' : null) : ' class="hidden"' ?>><?php Visualizer::converted($label) ?></dt>
	<dd class="<?php echo $member . (in_array($member, $visibility) ? null : " hidden") ?>">
		<?php Visualizer::converted($value ? $value : $i->{$member}) ?>
	</dd>
	<?php
}

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
	
	$visibility = explode(",", Cookie::getCookie(Cookie::LIST_VISIBILITY_KEY, $c->showPoint[Configuration::ON_SUBJECT]
		? "pageCount,readCount,size,evaluationCount,points,rate,dateTime"
		: "pageCount,readCount,size,evaluationCount,commentCount,dateTime"));
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
				<time class="dateTime<?php echo in_array("dateTime", $visibility) ? null : " hidden" ?>" pubdate="pubdate" datetime="<?php Visualizer::converted(date("c", $i->dateTime)) ?>">
					<?php Visualizer::converted(Visualizer::formatDateTime($i->dateTime)) ?>
				</time>
				<time class="lastUpdate<?php echo in_array("lastUpdate", $visibility) ? null : " hidden" ?>" datetime="<?php Visualizer::converted(date("c", $i->lastUpdate)) ?>">
					<?php Visualizer::converted(Visualizer::formatDateTime($i->lastUpdate)) ?>
				</time>
				<?php if (time() - $i->dateTime < $c->updatePeriod * 24 * 60 * 60): ?>
					<span class="update">
						NEW
					</span>
				<?php elseif (time() - $i->lastUpdate < $c->updatePeriod * 24 * 60 * 60): ?>
					<span class="update">
						UP
					</span>
				<?php endif ?>
				<?php if ($c->showName[Configuration::ON_SUBJECT]): ?>
					<a href="<?php Visualizer::converted(Visualizer::actionHref("author", $i->name)) ?>" class="name"><?php Visualizer::convertedName($i->name) ?></a>
				<?php endif ?>
				<?php if ($isAdmin): ?>
					<?php if ($c->showName[Configuration::ON_SUBJECT]): ?>
						<br />
					<?php endif ?>
					<span class="host"><?php Visualizer::converted($i->host) ?></span>
				<?php endif ?>
				<?php if ($c->showPages[Configuration::ON_SUBJECT] ||
					  $c->showSize[Configuration::ON_SUBJECT] ||
					  $c->showReadCount[Configuration::ON_SUBJECT] ||
					  $c->showPoint[Configuration::ON_SUBJECT] ||
					  $c->showComment[Configuration::ON_SUBJECT] ||
					  $c->showRate[Configuration::ON_SUBJECT]): ?>
				<dl>
					<?php $idx = 0; ?>
					<?php if ($c->showPages[Configuration::ON_SUBJECT]) entryInfo($idx, $i, $visibility, "ページ", "pageCount") ?>
					<?php if ($c->showSize[Configuration::ON_SUBJECT]) entryInfo($idx, $i, $visibility, "サイズ", "size", "{$i->size}KB") ?>
					<?php if ($c->showReadCount[Configuration::ON_SUBJECT]) entryInfo($idx, $i, $visibility, "閲覧", "readCount") ?>
					<?php if ($c->showPoint[Configuration::ON_SUBJECT] || $c->showRate[Configuration::ON_SUBJECT]): ?>
						<?php if ($c->showPoint[Configuration::ON_SUBJECT]): ?>
							<?php entryInfo($idx, $i, $visibility, "評価", "evaluationCount") ?>
						<?php endif ?>
						<?php if ($c->showComment[Configuration::ON_SUBJECT]): ?>
							<?php entryInfo($idx, $i, $visibility, "コメント", "commentCount") ?>
						<?php endif ?>
						<?php if ($c->showPoint[Configuration::ON_SUBJECT]): ?>
							<?php entryInfo($idx, $i, $visibility, "POINT", "points") ?>
						<?php endif ?>
						<?php if ($c->showRate[Configuration::ON_SUBJECT]) entryInfo($idx, $i, $visibility, "Rate", "rate", sprintf("%.2f", $i->rate)) ?>
					<?php endif ?>
				</dl>
				<?php endif ?>
				<?php if ($i->tags && $c->showTags[Configuration::ON_SUBJECT]): ?>
					<ul class="tags">
						<?php foreach ($i->tags as $j): ?>
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