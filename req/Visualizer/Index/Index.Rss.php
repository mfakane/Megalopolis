<?php
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
		<title><?+$c->title ?> - <?+$title ?></title>
		<link><?+Visualizer::absoluteHref() ?></link>
		<language>ja-JP</language>
		<description><?+$c->notes ?></description>
		<generator><?+App::NAME ?> <?+App::VERSION ?></generator>
		<?if ($h->entries): ?>
			<lastBuildDate><?+date("r", $h->lastUpdate) ?></lastBuildDate>
			<?foreach ($h->entries as $i): ?>
				<item>
					<?if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
						<title><?+$i->title ?></title>
					<?endif ?>
					<link><?+Visualizer::absoluteHref($i->subject, $i->id) ?></link>
					<pubDate><?+date("r", $i->dateTime) ?></pubDate>
					<?if ($c->showName[Configuration::ON_SUBJECT]): ?>
						<author><? Visualizer::convertedName($i->name) ?></author>
					<?endif ?>
					<?if ($c->useSummary && $c->showSummary[Configuration::ON_SUBJECT]): ?>
						<description><? Visualizer::convertedSummary($i->summary) ?></description>
					<?endif ?>
					<?if ($c->showTags[Configuration::ON_SUBJECT]): ?>
						<?foreach ($i->tags as $j): ?>
							<category domain="<?+Visualizer::absoluteHref("tag", $j . (strpos($j, ".") !== false ? ".html" : null)) ?>"><?+$j ?></category>
						<?endforeach ?>
					<?endif ?>
				</item>
			<?endforeach ?>
		<?endif ?>
	</channel>
</rss>
