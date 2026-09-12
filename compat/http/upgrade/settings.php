<?php
// Test-only configuration, intentionally valid in PHP 5.2 and PHP 8.4.
$upgradeSource = json_decode(file_get_contents('/upgrade-fixture/source.json'), true);
$config->adminHash = $upgradeSource['adminHash'];
$config->adminOnly = true;
$config->pointMap = array(10, 20, 30);
$config->commentPointMap = array(10, 20, 30);
$config->useSummary = true;
