<?php
error_reporting(E_ALL);

define("APP_DIR", "req/");
define("DATA_DIR", "store/");

define("CORE_DIR", "Core/");
define("HANDLER_DIR", "Handler/");
define("LIBRARY_DIR", "Library/");
define("MODEL_DIR", "Model/");
define("VISUALIZER_DIR", "Visualizer/");

define("DEFAULT_HANDLER", "index");
define("DEFAULT_ACTION", "index");
define("SQL_DEBUG", false);

require APP_DIR . "App.php";
?>