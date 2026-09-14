<?php
// Only this test image exposes the probe. Normal requests run the real front controller.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/__compat/health') {
    header('Content-Type: text/plain');
    echo 'ready';
    return;
}
if ($path === '/__compat/database') {
    define('COMPAT_DATABASE_PROBE', true);
}
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] = '/index.php';
require '/app/index.php';
