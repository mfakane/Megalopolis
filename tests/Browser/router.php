<?php

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
if ($path === "/__browser/health") {
	header("Content-Type: text/plain");
	echo "ready";
	return;
}

$path = is_string($path) ? rawurldecode($path) : "/";
$candidate = realpath(__DIR__ . $path);
$root = realpath(__DIR__);

if ($candidate !== false && $root !== false && is_file($candidate) && strncmp($candidate, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) === 0)
	return false;

$_SERVER["PHP_SELF"] = $_SERVER["SCRIPT_NAME"] = "/index.php";
require __DIR__ . "/index.php";
