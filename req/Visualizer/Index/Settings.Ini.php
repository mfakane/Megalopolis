<?php
$c = &Configuration::$instance;
$h = &IndexHandler::$instance;
$d = &Visualizer::$data;

function convert($s)
{
	return addcslashes(addslashes($s), '$\\');
}
?>
<?="<?php\r\n" ?>
define("TITLE", "<?=convert($c->title) ?>");
define("SHOW_CREDITS", false);
define("SKIN_DIR", is_mobile() ? "skin/mob" : "skin/lib");
define("USE_LAST_MODIFIED", false);
define("USE_HTML", true);
define("COMPUTE_RATE", false);
define("NEW_TIME", 259200);
define("SHOW_NUMBER", false);
define("SHOW_LASTUP", true);
define("SHOW_DATE_SECONDS", true);
define("SHOW_EDIT_COLUMN", false);
define("SHOW_POINT_COLUMN", <?=$c->showPoint[Configuration::ON_SUBJECT] ? "true" : "false" ?>);
define("SHOW_RATE_COLUMN", <?=$c->showRate[Configuration::ON_SUBJECT] ? "true" : "false" ?>);
define("SHOW_RATE_IN_PAGE", <?=$c->showRate[Configuration::ON_ENTRY] ? "true" : "false" ?>);
define("SHOW_SIZE", <?=$c->showSize[Configuration::ON_SUBJECT] ? "true" : "false" ?>);
define("USE_TAGS", <?=$c->showTags[Configuration::ON_SUBJECT] ? "true" : "false" ?>);
define("SHOW_TAGS", <?=$c->showTags[Configuration::ON_SUBJECT] ? 2 : 0 ?>);
define("SHOW_TAGS_TOP", true);
define("SHOW_TAGS_BOTTOM", false);
define("MAX_TAGS", <?=$c->maxTags ?>);
$selectable_tags = array();
define("AUTO_SUBJECTS", <?=$c->subjectSplitting ?>);
define("REVERSE_SUBJECTS_LIST", false);
define("SHOW_HOST", false);
$deny = array();
$denywrite = array();
define("BBQ_DENY_READ", <?=$c->useBBQ & Configuration::BBQ_READ ? "true" : "false" ?>);
define("BBQ_DENY_WRITE", <?=$c->useBBQ & Configuration::BBQ_WRITE ? "true" : "false" ?>);
define("DEFAULT_NAME", "<?=convert($c->defaultName) ?>");
define("ADMIN_ONLY", <?=$c->adminOnly ? "true" : "false" ?>);
define("COMMON_POST_PASS", "<?=convert($c->postPassword) ?>");
define("COMMON_PASS", "<?=convert($c->postPassword) ?>");
define("USE_ANONY_COMMON_PASS", true);
define("REQUIRE_COMMENT_PASS", <?=$c->requirePassword[Configuration::ON_COMMENT] ? "true" : "false" ?>);
define("EMPTY_COMMENT_PASS_DELETABLE", true);
define("USE_CRYPT", false);
$foregrounds = array(<?=$c->foregroundEnabled ? '"", ' . implode(", ", array_map(create_function('$_', 'return "\"" . convert($_) . "\"";'), $c->foregroundMap)) : null ?>);
$backgrounds = array(<?=$c->backgroundEnabled || $c->backgroundImageEnabled ? '"", ' . implode(", ", array_map(create_function('$_', 'return "\"" . convert($_) . "\"";'), array_merge($c->backgroundMap, $c->backgroundImageMap))) : null ?>);
$tg = array();
$ng = array();
$ng_comment = array();
define("SHOW_NGWORD", <?=$c->showDisallowedWords ? "true" : "false" ?>);
define("USE_POINT", <?=$c->usePoints() ? "true" : "false" ?>);
define("USE_COMMENT", true);
define("COMPUTE_FREE", false);
define("EVALUATE_FREE", false);
$pointtable = array(<?=$c->commentPointMap ? implode(", ", array_merge(array(0), array_reverse($c->commentPointMap))) : null ?>);
$anonypoints = array(<?=implode(", ", array_reverse($c->pointMap)) ?>);
define("NOTE_TITLE", "留意事項");
define("RECENT_COUNT", 3);
define("RECENT_TARGET", "_top");
define("DISPLAY_SUBJECT", <?=$c->showTitle[Configuration::ON_SUBJECT] ? "true" : "false" ?>);
define("DISPLAY_NAME", <?=$c->showName[Configuration::ON_SUBJECT] ? "true" : "false" ?>);
define("DISPLAY_RES", <?=$c->useAnyPoints() || $c->showComment[Configuration::ON_SUBJECT] ? "true" : "false" ?>);
define("DISPLAY_POINT", <?=$c->showPoint[Configuration::ON_COMMENT] ? "true" : "false" ?>);
define("DISPLAY_ALL_POINT", true);
define("DISPLAY_EDIT", true);
define("DISPLAY_SEARCH", DISPLAY_SUBJECT && DISPLAY_NAME && DISPLAY_RES);
define("USE_MBSTRING", extension_loaded("mbstring"));
?>