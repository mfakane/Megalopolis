<?php
function entryInfo(&$idx, $i, $visibility, $label, $member, $value)
{
	$visible = isset($visibility[$member]);
	
	?>
	<dt<?php echo $visible ? ($idx++ == 0 ? ' class="firstChild"' : null) : ' class="hidden"' ?>><?php Visualizer::converted($label) ?></dt>
	<dd class="<?php echo $member . ($visible ? null : " hidden") ?>">
		<?php Visualizer::converted($value) ?>
	</dd>
	<?php
}

function entryInfoHeaderSingle($visibility, $member, $header)
{
	?>
	<th class="info <?php echo $member ?><?php echo isset($visibility[$member]) ? null : " hidden" ?>">
		<?php Visualizer::converted($header) ?>
	</th>
	<?php
}

function entryInfoSingle($i, $visibility, $member, $value)
{
	?>
	<td class="info <?php echo $member ?><?php echo isset($visibility[$member]) ? null : " hidden" ?>">
		<?php Visualizer::converted($value) ?>
	</td>
	<?php
}

function filterVisibleOnly($visibility, $arr)
{
	$rt = array();
	
	foreach ($visibility as $k => $v)
		if (isset($arr[$k]))
			$rt[$k] = $arr[$k];
	
	return $rt;
}

function entries($entries, $isAdmin, $listType = null)
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
	$visibility = array_filter(explode(",", Cookie::getCookie(Cookie::LIST_VISIBILITY_KEY, "readCount,size,commentCount,evaluationCount,points,rate,dateTime")));
	$visibility = array_flip($visibility);
	
	if ($listType == "double")
	{
	?>
		<div class="entries" id="entries">
			<?php foreach ($entries as $idx => $i): ?>
				<article>
					<div class="articleBody">
						<?php if ($isAdmin || $c->showTitle[Configuration::ON_SUBJECT]): ?>
							<h2>
								<?php if ($isAdmin): ?>
									<label>
										<input type="checkbox" name="id[]" value="<?php Visualizer::converted($i->id) ?>" />
								<?php endif ?>
								<a href="<?php Visualizer::converted(Visualizer::actionHrefArray(array($i->subject, $i->id))) ?>"><?php Visualizer::converted($i->title) ?></a>
								<?php if ($isAdmin): ?>
									</label>
								<?php endif ?>
							</h2>
						<?php endif ?>
						<time class="lastUpdate<?php echo isset($visibility["lastUpdate"]) ? null : " hidden" ?>" datetime="<?php Visualizer::converted(date("c", $i->getLastUpdate())) ?>">
							<?php Visualizer::converted(Visualizer::formatDateTime($i->getLastUpdate())) ?>
							<span class="value hidden"><?php Visualizer::converted($i->getLastUpdate()) ?></span>
						</time>
						<time class="dateTime<?php echo isset($visibility["dateTime"]) ? null : " hidden" ?>" pubdate="pubdate" datetime="<?php Visualizer::converted(date("c", $i->dateTime)) ?>">
							<?php Visualizer::converted(Visualizer::formatDateTime($i->dateTime)) ?>
							<span class="value hidden"><?php Visualizer::converted($i->dateTime) ?></span>
						</time>
						<?php if (time() - $i->dateTime < $c->updatePeriod * 24 * 60 * 60): ?>
							<span class="update">
								NEW
							</span>
						<?php elseif (time() - $i->getLastUpdate() < $c->updatePeriod * 24 * 60 * 60): ?>
							<span class="update">
								UP
							</span>
						<?php endif ?>
						<?php if ($isAdmin || $c->showName[Configuration::ON_SUBJECT]): ?>
							<a href="<?php Visualizer::converted(Visualizer::actionHrefArray(array("author", $i->name))) ?>" class="name"><?php Visualizer::convertedName($i->name) ?></a>
						<?php endif ?>
						<?php if ($isAdmin): ?>
							<br />
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
							<?php if ($c->showPages[Configuration::ON_SUBJECT]) entryInfo($idx, $i, $visibility, "ページ", "pageCount", $i->pageCount) ?>
							<?php if ($c->showSize[Configuration::ON_SUBJECT]) entryInfo($idx, $i, $visibility, "サイズ", "size", "{$i->size}KB") ?>
							<?php if ($c->showReadCount[Configuration::ON_SUBJECT]) entryInfo($idx, $i, $visibility, "閲覧", "readCount", $i->readCount) ?>
							<?php if ($c->showPoint[Configuration::ON_SUBJECT]) entryInfo($idx, $i, $visibility, "評価", "evaluationCount", $c->pointMap && $c->commentPointMap ? "{$i->commentedEvaluationCount}/{$i->evaluationCount}" : $i->evaluationCount) ?>
							<?php if ($c->showComment[Configuration::ON_SUBJECT]) entryInfo($idx, $i, $visibility, "コメント", "commentCount", $i->commentCount) ?>
							<?php if ($c->showPoint[Configuration::ON_SUBJECT]) entryInfo($idx, $i, $visibility, "POINT", "points", $i->points) ?>
							<?php if ($c->showRate[Configuration::ON_SUBJECT]) entryInfo($idx, $i, $visibility, "Rate", "rate", sprintf("%.2f", $i->rate)) ?>
						</dl>
						<?php endif ?>
						<?php if ($i->tags && $c->showTags[Configuration::ON_SUBJECT]): ?>
							<ul class="tags">
								<?php foreach ($i->tags as $j): ?>
									<li>
										<a href="<?php Visualizer::converted(Visualizer::actionHrefArray(array("tag", $j))) ?>"><?php Visualizer::converted($j) ?></a>
									</li>
								<?php endforeach ?>
							</ul>
						<?php endif ?>
						<?php if (!Util::isEmpty($i->summary) && $c->useSummary && $c->showSummary[Configuration::ON_SUBJECT]): ?>
							<p>
								<?php Visualizer::convertedSummary($i->summary) ?>
							</p>
						<?php endif ?>
					</div>
				</article>
			<?php endforeach ?>
		</div>
	<?php
	}
	else
	{
		$spanWidth = count(array_filter(array
		(
			"check" => $isAdmin,
			"title" => $isAdmin || $c->showTitle[Configuration::ON_SUBJECT],
			"name" => $isAdmin || $c->showName[Configuration::ON_SUBJECT],
		))) + count(filterVisibleOnly($visibility, array
		(
			"dateTime" => true,
			"lastUpdate" => true,
		))) + count(filterVisibleOnly($visibility, array_filter(array
		(
			"pageCount" => $c->showPages[Configuration::ON_SUBJECT],
			"size" => $c->showSize[Configuration::ON_SUBJECT],
			"readCount" => $c->showReadCount[Configuration::ON_SUBJECT],
			"evaluationCount" => $c->showPoint[Configuration::ON_SUBJECT],
			"commentCount" => $c->showComment[Configuration::ON_SUBJECT],
			"points" => $c->showPoint[Configuration::ON_SUBJECT],
			"rate" => $c->showRate[Configuration::ON_SUBJECT]
		))));
		?>
		<section class="entries" id="entries">
			<table>
				<thead>
					<tr>
						<?php if ($isAdmin): ?>
							<th class="checkbox">
							</th>
						<?php endif ?>
						<?php if ($isAdmin || $c->showTitle[Configuration::ON_SUBJECT]): ?>
							<th class="title">
								作品名
							</th>
						<?php endif ?>
						<?php if ($isAdmin || $c->showName[Configuration::ON_SUBJECT]): ?>
							<th class="name">
								名前
							</th>
						<?php endif ?>
						<th class="dateTime<?php echo isset($visibility["dateTime"]) ? null : " hidden" ?>">
							投稿日時
						</th>
						<th class="lastUpdate<?php echo isset($visibility["lastUpdate"]) ? null : " hidden" ?>">
							更新日時
						</th>
						<?php if ($c->showPages[Configuration::ON_SUBJECT]) entryInfoHeaderSingle($visibility, "pageCount", "ページ") ?>
						<?php if ($c->showSize[Configuration::ON_SUBJECT]) entryInfoHeaderSingle($visibility, "size", "サイズ") ?>
						<?php if ($c->showReadCount[Configuration::ON_SUBJECT]) entryInfoHeaderSingle($visibility, "readCount", "閲覧") ?>
						<?php if ($c->showPoint[Configuration::ON_SUBJECT]) entryInfoHeaderSingle($visibility, "evaluationCount", "評価") ?>
						<?php if ($c->showComment[Configuration::ON_SUBJECT]) entryInfoHeaderSingle($visibility, "commentCount", "コメント") ?>
						<?php if ($c->showPoint[Configuration::ON_SUBJECT]) entryInfoHeaderSingle($visibility, "points", "POINT") ?>
						<?php if ($c->showRate[Configuration::ON_SUBJECT]) entryInfoHeaderSingle($visibility, "rate", "Rate") ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($entries as $idx => $i): ?>
						<tr class="article<?php if (!$c->showTags[Configuration::ON_SUBJECT] && Util::isEmpty($i->summary)) { echo ' notags'; } else if (!Util::isEmpty($i->summary)) { echo ' hasSummary'; } ?>" id="article<?=$i->id ?>">
							<?php if ($isAdmin): ?>
								<td class="checkbox">
									<input type="checkbox" name="id[]" value="<?php Visualizer::converted($i->id) ?>" />
								</td>
							<?php endif ?>
							<?php if ($isAdmin || $c->showTitle[Configuration::ON_SUBJECT]): ?>
								<td class="title">
									<?php if (time() - $i->dateTime < $c->updatePeriod * 24 * 60 * 60): ?>
										<span class="update">
											NEW
										</span>
									<?php elseif (time() - $i->getLastUpdate() < $c->updatePeriod * 24 * 60 * 60): ?>
										<span class="update">
											UP
										</span>
									<?php endif ?>
									<h2>
										<a href="<?php Visualizer::converted(Visualizer::actionHrefArray(array($i->subject, $i->id))) ?>"><?php Visualizer::converted($i->title) ?></a>
									</h2>
									<?php if ($isAdmin): ?>
										<?php if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
											<br />
										<?php endif ?>
										<span class="host"><?php Visualizer::converted($i->host) ?></span>
									<?php endif ?>
								</td>
							<?php endif ?>
							<?php if ($isAdmin || $c->showName[Configuration::ON_SUBJECT]): ?>
								<td class="name">
									<a href="<?php Visualizer::converted(Visualizer::actionHrefArray(array("author", $i->name))) ?>"><?php Visualizer::convertedName($i->name) ?></a>
								</td>
							<?php endif ?>
							<td class="dateTime<?php echo isset($visibility["dateTime"]) ? null : " hidden" ?>">
								<?php Visualizer::converted(substr(Visualizer::formatDateTime($i->dateTime), 2, -3)) ?>
								<span class="value hidden"><?php Visualizer::converted($i->dateTime) ?></span>
							</td>
							<td class="lastUpdate<?php echo isset($visibility["lastUpdate"]) ? null : " hidden" ?>">
								<?php Visualizer::converted(substr(Visualizer::formatDateTime($i->getLastUpdate()), 2, -3)) ?>
								<span class="value hidden"><?php Visualizer::converted($i->getLastUpdate()) ?></span>
							</td>
							<?php if ($c->showPages[Configuration::ON_SUBJECT]) entryInfoSingle($i, $visibility, "pageCount", $i->pageCount) ?>
							<?php if ($c->showSize[Configuration::ON_SUBJECT]) entryInfoSingle($i, $visibility, "size", "{$i->size}KB") ?>
							<?php if ($c->showReadCount[Configuration::ON_SUBJECT]) entryInfoSingle($i, $visibility, "readCount", $i->readCount) ?>
							<?php if ($c->showPoint[Configuration::ON_SUBJECT]) entryInfoSingle($i, $visibility, "evaluationCount", $c->pointMap && $c->commentPointMap ? "{$i->commentedEvaluationCount}/{$i->evaluationCount}" : $i->evaluationCount) ?>
							<?php if ($c->showComment[Configuration::ON_SUBJECT]) entryInfoSingle($i, $visibility, "commentCount", $i->commentCount) ?>
							<?php if ($c->showPoint[Configuration::ON_SUBJECT]) entryInfoSingle($i, $visibility, "points", $i->points) ?>
							<?php if ($c->showRate[Configuration::ON_SUBJECT]) entryInfoSingle($i, $visibility, "rate", sprintf("%.2f", $i->rate)) ?>
						</tr>
						<?php if ($c->showTags[Configuration::ON_SUBJECT] || !Util::isEmpty($i->summary)): ?>
							<tr class="tags" id="tags<?=$i->id ?>">
								<td colspan="<?php echo $spanWidth ?>">
									<?php if (!Util::isEmpty($i->summary)): ?>
										<a href="javascript:void(0);" class="summaryButton">[概要]</a>
									<?php endif ?>
									<ul>
										<?php if ($i->tags): ?>
											<?php foreach ($i->tags as $j): ?>
												<li>
													<a href="<?php Visualizer::converted(Visualizer::actionHrefArray(array("tag", $j))) ?>"><?php Visualizer::converted($j) ?></a>
												</li>
											<?php endforeach ?>
										<?php endif ?>
									</ul>
									<?php if (!Util::isEmpty($i->summary)): ?>
										<p class="hidden">
											<?php Visualizer::convertedSummary($i->summary) ?>
										</p>
									<?php endif ?>
								</td>
							</tr>
						<?php endif ?>
					<?php endforeach ?>
				</tbody>
			</table>
			<script>
				megalopolis.index.showSummary();
			</script>
		</section>
		<?php
	}
}
?>