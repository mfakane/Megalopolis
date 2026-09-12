<?php
// PHP 5.2-compatible runtime evidence, executed via CLI (does not open test DBs).
echo 'PHP ' . PHP_VERSION . ' ' . PHP_SAPI . "\n";
echo 'PDO drivers: ' . implode(',', PDO::getAvailableDrivers()) . "\n";
echo 'SQLite ' . (newRuntimeSQLiteVersion()) . "\n";
echo 'Extensions: ' . implode(',', get_loaded_extensions()) . "\n";
function newRuntimeSQLiteVersion()
{
    $db = new PDO('sqlite::memory:');
    return $db->query('SELECT sqlite_version()')->fetchColumn();
}
