<?php
namespace Megalopolis;

Configuration::$instance = new Configuration();
$config = Configuration::$instance;
require __DIR__ . '/compat/http/settings.php';
$config->dataStore = getenv('COMPAT_DRIVER') === 'mysql'
    ? new MySQLDataStore('megalopolis_r46', [getenv('COMPAT_MYSQL_HOST'), 3306], 'fixture', 'fixture')
    : new SQLiteDataStore('/fixtures');
if (defined('COMPAT_DATABASE_PROBE')) {
    require __DIR__ . '/compat/http/probe.php';
    exit;
}
