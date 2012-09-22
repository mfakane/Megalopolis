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
<feed xmlns="http://www.w3.org/2005/Atom">
	<title><?+$c->title ?> - <?+$title ?></title>
	<link href="<?+Visualizer::absoluteHref() ?>" />
	<generator version="<?+App::VERSION ?>"><?+App::NAME ?></generator>
	<?if ($h->entries): ?>
		<updated><?+date("c", $h->lastUpdate) ?></updated>
		<?foreach ($h->entries as $i): ?>
			<entry>
				<?if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
					<title><?+$i->title ?></title>
				<?endif ?>
				<?if ($c->showName[Configuration::ON_SUBJECT]): ?>
					<author>
						<name><? Visualizer::convertedName($i->name) ?></name>
						<?if (!Util::isEmpty($i->mail)): ?>
							<email><?+$i->mail ?></email>
						<?endif ?>
						<?if (!Util::isEmpty($i->link)): ?>
							<uri><?+$i->link ?></uri>
						<?endif ?>
					</author>
				<?endif ?>
				<link href="<?+Visualizer::absoluteHref($i->subject, $i->id) ?>"/>
				<published><?+date("c", $i->dateTime) ?></published>
				<updated><?+date("c", $i->lastUpdate) ?></updated>
				<?if ($c->useSummary && $c->showSummary[Configuration::ON_SUBJECT]): ?>
					<summary><? Visualizer::convertedSummary($i->summary) ?></summary>
				<?endif ?>
				<?if ($c->showTags[Configuration::ON_SUBJECT]): ?>
					<?foreach ($i->tags as $j): ?>
						<category term="<?+$j ?>" scheme="<?+Visualizer::absoluteHref("tag", $j . (strpos($j, ".") !== false ? ".html" : null)) ?>" />
					<?endforeach ?>
				<?endif ?>
			</entry>
		<?endforeach ?>
	<?endif ?>
</feed>