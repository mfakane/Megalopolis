<?php
Configuration::$instance = new Configuration();
$config = Configuration::$instance;
require '/harness/settings.php';
$config->dataStore = getenv('COMPAT_DRIVER') === 'mysql'
    ? new MySQLDataStore('megalopolis_r46', array(getenv('COMPAT_MYSQL_HOST'), 3306), 'fixture', 'fixture')
    : new SQLiteDataStore('/fixtures');
