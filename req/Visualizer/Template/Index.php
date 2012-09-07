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

function entryInfoHeaderSingle($visibility, $member, $header)
{
	?>
	<th class="info <?php echo $member ?><?php echo in_array($member, $visibility) ? null : " hidden" ?>">
		<?php Visualizer::converted($header) ?>
	</th>
	<?php
}

function entryInfoSingle($i, $visibility, $member, $value = null)
{
	?>
	<td class="info <?php echo $member ?><?php echo in_array($member, $visibility) ? null : " hidden" ?>">
		<?php Visualizer::converted($value ? $value : $i->{$member}) ?>
	</td>
	<?php
}

function filterVisibleOnly($visibility, $arr)
{
	$rt = array();
	
	foreach ($visibility as $i)
		if (isset($arr[$i]))
			$rt[$i] = $arr[$i];
	
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
	
	$visibility = explode(",", Cookie::getCookie(Cookie::LIST_VISIBILITY_KEY, $c->showPoint[Configuration::ON_SUBJECT]
		? "pageCount,readCount,size,evaluationCount,points,rate,dateTime"
		: "pageCount,readCount,size,evaluationCount,commentCount,dateTime"));
	
	if ($listType == "double")
	{
	?>
		<div class="entries">
			<?php foreach ($entries as $idx => $i): ?>
				<article>
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
						<span class="value hidden"><?php Visualizer::converted($i->dateTime) ?></span>
					</time>
					<time class="lastUpdate<?php echo in_array("lastUpdate", $visibility) ? null : " hidden" ?>" datetime="<?php Visualizer::converted(date("c", $i->lastUpdate)) ?>">
						<?php Visualizer::converted(Visualizer::formatDateTime($i->lastUpdate)) ?>
						<span class="value hidden"><?php Visualizer::converted($i->lastUpdate) ?></span>
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
				</article>
			<?php endforeach ?>
		</div>
	<?php
	}
	else
	{
		$spanWidth = count(array_filter(array
		(
			"title" => $c->showTitle[Configuration::ON_SUBJECT],
			"name" => $c->showName[Configuration::ON_SUBJECT],
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
		<section class="entries">
			<table>
				<thead>
					<tr>
						<?php if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
							<th class="title">
								作品名
							</th>
						<?php endif ?>
						<?php if ($c->showName[Configuration::ON_SUBJECT]): ?>
							<th class="name">
								名前
							</th>
						<?php endif ?>
						<th class="dateTime<?php echo in_array("dateTime", $visibility) ? null : " hidden" ?>">
							投稿日時
						</th>
						<th class="lastUpdate<?php echo in_array("lastUpdate", $visibility) ? null : " hidden" ?>">
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
						<tr class="article<?php if (!$c->showTags[Configuration::ON_SUBJECT] && Util::isEmpty($i->summary)) echo ' notags' ?>">
							<?php if ($c->showTitle[Configuration::ON_SUBJECT] || $isAdmin): ?>
								<td class="title">
									<?php if (time() - $i->dateTime < $c->updatePeriod * 24 * 60 * 60): ?>
										<span class="update">
											NEW
										</span>
									<?php elseif (time() - $i->lastUpdate < $c->updatePeriod * 24 * 60 * 60): ?>
										<span class="update">
											UP
										</span>
									<?php endif ?>
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
									<?php if ($isAdmin): ?>
										<?php if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
											<br />
										<?php endif ?>
										<span class="host"><?php Visualizer::converted($i->host) ?></span>
									<?php endif ?>
								</td>
							<?php endif ?>
							<?php if ($c->showName[Configuration::ON_SUBJECT]): ?>
								<td class="name">
									<a href="<?php Visualizer::converted(Visualizer::actionHref("author", $i->name)) ?>"><?php Visualizer::convertedName($i->name) ?></a>
								</td>
							<?php endif ?>
							<td class="dateTime<?php echo in_array("dateTime", $visibility) ? null : " hidden" ?>">
								<?php Visualizer::converted(substr(Visualizer::formatDateTime($i->dateTime), 2, -3)) ?>
								<span class="value hidden"><?php Visualizer::converted($i->dateTime) ?></span>
							</td>
							<td class="lastUpdate<?php echo in_array("lastUpdate", $visibility) ? null : " hidden" ?>">
								<?php Visualizer::converted(substr(Visualizer::formatDateTime($i->lastUpdate), 2, -3)) ?>
								<span class="value hidden"><?php Visualizer::converted($i->lastUpdate) ?></span>
							</td>
							<?php if ($c->showPages[Configuration::ON_SUBJECT]) entryInfoSingle($i, $visibility, "pageCount") ?>
							<?php if ($c->showSize[Configuration::ON_SUBJECT]) entryInfoSingle($i, $visibility, "size", "{$i->size}KB") ?>
							<?php if ($c->showReadCount[Configuration::ON_SUBJECT]) entryInfoSingle($i, $visibility, "readCount") ?>
							<?php if ($c->showPoint[Configuration::ON_SUBJECT]) entryInfoSingle($i, $visibility, "evaluationCount") ?>
							<?php if ($c->showComment[Configuration::ON_SUBJECT]) entryInfoSingle($i, $visibility, "commentCount") ?>
							<?php if ($c->showPoint[Configuration::ON_SUBJECT]) entryInfoSingle($i, $visibility, "points") ?>
							<?php if ($c->showRate[Configuration::ON_SUBJECT]) entryInfoSingle($i, $visibility, "rate", sprintf("%.2f", $i->rate)) ?>
						</tr>
						<?php if ($c->showTags[Configuration::ON_SUBJECT] || !Util::isEmpty($i->summary)): ?>
							<tr class="tags">
								<td colspan="<?php echo $spanWidth ?>">
									<?php if (!Util::isEmpty($i->summary)): ?>
										<script>
											megalopolis.index.showSummary();
										</script>
									<?php endif ?>
									<ul>
										<?php if ($i->tags): ?>
											<?php foreach ($i->tags as $j): ?>
												<li>
													<a href="<?php Visualizer::converted(Visualizer::actionHref("tag", $j)) ?>"><?php Visualizer::converted($j) ?></a>
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
		</section>
		<?php
	}
}
?>