<?php
// Shared settings, deliberately PHP 5.2-compatible. Never used in production.
$config->linkType = 3; // LINK_QUERY: no web-server rewrites required.
$config->useOutputCompression = false;
$config->htaccessAutoConfig = false;
$config->utilsEnabled = false;
$config->storeSessionIntoDataStore = false;
$config->subjectSplitting = 100;
$config->useSummary = true;
foreach (array('showTitle', 'showName', 'showSummary', 'showPages', 'showSize',
    'showPoint', 'showComment', 'showReadCount', 'showTags') as $property)
    $config->$property = array('Entry' => true, 'Subject' => true, 'Author' => true,
        'Tag' => true, 'Comment' => true);
