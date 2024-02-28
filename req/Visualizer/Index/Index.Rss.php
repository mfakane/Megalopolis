<?php
namespace Megalopolis;

$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;

if (App::$actionName == "tag")
	$title = "タグ: {$d}";
else if (App::$actionName == "author")
	$title = "作者: {$d}";
else
	$title = $h->subject == $h->subjectCount ? "最新作品集" : "作品集 {$h->subject}";
?>
<?='<?xml version="1.0" encoding="UTF-8"?>' ?>
<rss version="2.0">
	<channel>
		<title><?=Visualizer::escapeOutput($c->title) ?> - <?=Visualizer::escapeOutput($title) ?></title>
		<link><?=Visualizer::escapeOutput(Visualizer::absoluteHref()) ?></link>
		<language>ja-JP</language>
		<description><?=Visualizer::escapeOutput($c->notes) ?></description>
		<generator><?=Visualizer::escapeOutput(App::NAME) ?> <?=Visualizer::escapeOutput(App::VERSION) ?></generator>
		<?php if ($h->entries): ?>
			<?php if ($h->lastUpdate): ?>
				<lastBuildDate><?=Visualizer::escapeOutput(date("r", $h->lastUpdate)) ?></lastBuildDate>
			<?php endif ?>
			<?php foreach ($h->entries as $i): ?>
				<item>
					<?php if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
						<title><?=Visualizer::escapeOutput($i->title) ?></title>
					<?php endif ?>
					<link><?=Visualizer::escapeOutput(Visualizer::absoluteHref($i->subject, $i->id)) ?></link>
					<pubDate><?=Visualizer::escapeOutput(date("r", $i->dateTime)) ?></pubDate>
					<?php if ($c->showName[Configuration::ON_SUBJECT]): ?>
						<author><?php Visualizer::convertedName($i->name) ?></author>
					<?php endif ?>
					<?php if ($c->useSummary && $c->showSummary[Configuration::ON_SUBJECT]): ?>
						<description><?php Visualizer::convertedSummary($i->summary ?? "") ?></description>
					<?php endif ?>
					<?php if ($c->showTags[Configuration::ON_SUBJECT]): ?>
						<?php foreach ($i->tags as $j): ?>
							<category domain="<?=Visualizer::escapeOutput(Visualizer::absoluteHref("tag", $j . (strpos($j, ".") !== false ? ".html" : ""))) ?>"><?=Visualizer::escapeOutput($j) ?></category>
						<?php endforeach ?>
					<?php endif ?>
				</item>
			<?php endforeach ?>
		<?php endif ?>
	</channel>
</rss>
