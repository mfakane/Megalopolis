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
<feed xmlns="http://www.w3.org/2005/Atom">
	<title><?=Visualizer::escapeOutput($c->title) ?> - <?=Visualizer::escapeOutput($title) ?></title>
	<link href="<?=Visualizer::escapeOutput(Visualizer::absoluteHref()) ?>" />
	<generator version="<?=Visualizer::escapeOutput(App::VERSION) ?>"><?=Visualizer::escapeOutput(App::NAME) ?></generator>
	<?php if ($h->entries): ?>
		<?php if ($h->lastUpdate): ?>
			<updated><?=Visualizer::escapeOutput(date("c", $h->lastUpdate)) ?></updated>
		<?php endif ?>
		<?php foreach ($h->entries as $i): ?>
			<entry>
				<?php if ($c->showTitle[Configuration::ON_SUBJECT]): ?>
					<title><?=Visualizer::escapeOutput($i->title) ?></title>
				<?php endif ?>
				<?php if ($c->showName[Configuration::ON_SUBJECT]): ?>
					<author>
						<name><?php Visualizer::convertedName($i->name) ?></name>
						<?php if (!Util::isEmpty($i->mail)): ?>
							<email><?=Visualizer::escapeOutput($i->mail) ?></email>
						<?php endif ?>
						<?php if (!Util::isEmpty($i->link)): ?>
							<uri><?=Visualizer::escapeOutput($i->link) ?></uri>
						<?php endif ?>
					</author>
				<?php endif ?>
				<link href="<?=Visualizer::escapeOutput(Visualizer::absoluteHref($i->subject, $i->id)) ?>"/>
				<published><?=Visualizer::escapeOutput(date("c", $i->dateTime)) ?></published>
				<updated><?=Visualizer::escapeOutput(date("c", $i->lastUpdate)) ?></updated>
				<?php if ($c->useSummary && $c->showSummary[Configuration::ON_SUBJECT]): ?>
					<summary><?php Visualizer::convertedSummary($i->summary ?? "") ?></summary>
				<?php endif ?>
				<?php if ($c->showTags[Configuration::ON_SUBJECT]): ?>
					<?php foreach ($i->tags as $j): ?>
						<category term="<?=Visualizer::escapeOutput($j) ?>" scheme="<?=Visualizer::escapeOutput(Visualizer::absoluteHref("tag", $j . (strpos($j, ".") !== false ? ".html" : ""))) ?>" />
					<?php endforeach ?>
				<?php endif ?>
			</entry>
		<?php endforeach ?>
	<?php endif ?>
</feed>
