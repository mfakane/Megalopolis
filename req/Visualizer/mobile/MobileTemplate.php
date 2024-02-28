<?php
namespace Megalopolis;

class MobileTemplate
{
	static function makeMenu(string $basePath, string $current): void
	{
		echo '<ul>';
		
		foreach (array
		(
			"h" => "作品一覧, home",
			"s" => Configuration::$instance->showTitle[Configuration::ON_SUBJECT] && Configuration::$instance->useSearch ? "検索, search" : null,
			"recent" => "履歴, history",
			"m" => "その他, more"
		) as $k => $v)
		{
			if (!$v)
				continue;
			
			list($n, $i) = explode(", ", $v);
		?>
			<li>
				<a href="<?php Visualizer::converted($k == "recent" ? Visualizer::absoluteHref($k) : rtrim($basePath, "/") . "/" . $k) ?>"
				data-transition="none"
				<?php if ($k == $current) echo ' class="ui-btn-active"' ?>
				data-icon="<?php echo $i ?>">
					<?php Visualizer::converted($n) ?>
				</a>
			</li>
		<?php
		}
		
		echo '</ul>';
	}

	/**
	 * @param ThreadEntry[] $entries
	 */
	static function entries(array $entries, Configuration $c): void
	{
		if (!$entries)
			return;
		
		foreach ($entries as $i)
		{
		?>
			<?php if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
				<li>
					<a href="<?php Visualizer::converted(Visualizer::actionHref($i->subject, $i->id)) ?>">
						<h2 class="title"><?php Visualizer::converted($i->title) ?></h2>
						<?php if ($c->showName[Configuration::ON_SUBJECT]): ?>
							<p class="name"><?php Visualizer::convertedName($i->name) ?></p>
						<?php endif ?>
						<span class="dateTime"><?php Visualizer::converted(Visualizer::formatShortDateTime($i->dateTime)) ?><span class="dateTimeValue"><?php Visualizer::converted(strval($i->dateTime)) ?></span></span>
						<ul class="info">
							<?php if ($c->showComment[Configuration::ON_SUBJECT]): ?>
								<li>コメント <span class="commentCount"><?php Visualizer::converted(strval($i->commentCount)) ?></span></li>
							<?php endif ?>
							<?php if ($c->showPoint[Configuration::ON_SUBJECT]): ?>
								<li>POINT <span class="points"><?php Visualizer::converted(strval($i->points)) ?></span></li>
							<?php endif ?>
							<?php if ($c->showRate[Configuration::ON_SUBJECT]): ?>
								<li>Rate <span class="rate"><?php Visualizer::converted(sprintf("%.2f", $i->rate)) ?></span></li>
							<?php endif ?>
							<?php if (!$c->showComment[Configuration::ON_SUBJECT]) echo "<li></li>" ?>
							<?php if (!$c->showPoint[Configuration::ON_SUBJECT]) echo "<li></li>" ?>
							<?php if (!$c->showRate[Configuration::ON_SUBJECT]) echo "<li></li>" ?>
							<?php if ($c->showSize[Configuration::ON_SUBJECT]): ?>
								<li class="size"><?php Visualizer::converted(strval($i->size)) ?>KB</li>
							<?php endif ?>
						</ul>
						<?php if ($i->tags && $c->showTags[Configuration::ON_SUBJECT]): ?>
							<ul class="tags">
								<?php foreach ($i->tags as $j): ?>
									<li><?php Visualizer::converted($j) ?></li>
								<?php endforeach ?>
							</ul>
						<?php endif ?>
					</a>
				</li>
			<?php endif ?>
		<?php
		}
	}
}
