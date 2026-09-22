<?php
namespace Megalopolis;

$config = new Configuration();
Configuration::$instance = $config;

$config->title = "Megalopolis";
$config->adminHash = Util::hash("browser-admin");
$config->utilsEnabled = false;
$config->htaccessAutoConfig = false;
$config->linkType = Configuration::LINK_QUERY;
$config->useOutputCompression = false;
$config->useSearch = true;
$config->dataStore = new SQLiteDataStore();

$config->useComments = true;
$config->pointMap = array(50, 40, 30, 20, 10);
$config->commentPointMap = array(50, 40, 30, 20, 10);
$config->adminOnly = true;
$config->requireName = array(
	Configuration::ON_ENTRY => true,
	Configuration::ON_COMMENT => true,
);
$config->requirePassword = array(
	Configuration::ON_ENTRY => true,
	Configuration::ON_COMMENT => true,
);
$config->maxTags = 10;

$config->notes = "Hello!";
$config->footers = array(
	'<a href="' . Visualizer::actionHref("recent") . '">閲覧履歴</a>',
	'<a href="' . Visualizer::actionHref("tag") . '">タグ一覧</a>',
	'<a href="' . Visualizer::actionHref("author") . '">作者一覧</a>',
	'<a href="' . Visualizer::actionHref("config.html") . '">設定情報</a>',
);
