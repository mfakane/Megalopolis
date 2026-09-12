<?php
namespace Megalopolis;

Configuration::$instance = new Configuration();
$config = Configuration::$instance;
require __DIR__ . '/compat/http/settings.php';
$config->dataStore = getenv('COMPAT_DRIVER') === 'mysql'
    ? new MySQLDataStore('megalopolis_r46', [getenv('COMPAT_MYSQL_HOST'), 3306], 'fixture', 'fixture')
    : new SQLiteDataStore('/fixtures');
if (getenv('COMPAT_UPGRADE') === '1') {
    require __DIR__ . '/compat/http/upgrade/settings.php';
    if (isset($_GET['upgradeSnapshot'])) {
        require __DIR__ . '/compat/http/upgrade/probe.php';
        exit;
    }
}
if (defined('COMPAT_DATABASE_PROBE')) {
    require __DIR__ . '/compat/http/probe.php';
    exit;
}
