<?php
namespace Megalopolis;

require_once __DIR__ . "/Core/App.php";

App::$startTime = microtime(true);
App::load(Constant::CORE_DIR . "Util");
App::precondition();

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . "/..");
$dotenv->safeLoad();

App::load(array(
	Constant::CORE_DIR . "Auth",
	Constant::CORE_DIR . "Configuration",
	Constant::CORE_DIR . "Cookie",
	Constant::CORE_DIR . "DataStore",
	Constant::CORE_DIR . "Handler",
	Constant::CORE_DIR . "SessionStore",
	Constant::CORE_DIR . "Visualizer",
	Constant::MODEL_DIR . "Board",
	Constant::MODEL_DIR . "Comment",
	Constant::MODEL_DIR . "Evaluation",
	Constant::MODEL_DIR . "Meta",
	Constant::MODEL_DIR . "SearchIndex",
	Constant::MODEL_DIR . "SearchIndex/Classic",
	Constant::MODEL_DIR . "SearchIndex/SQLite",
	Constant::MODEL_DIR . "SearchIndex/MySQL",
	Constant::MODEL_DIR . "Statistics",
	Constant::MODEL_DIR . "ThreadEntry",
	Constant::MODEL_DIR . "Thread"
));
App::load("../config");

if (!Configuration::$instance->dataStore)
	Configuration::$instance->dataStore = new SQLiteDataStore();

Auth::useSession();
App::main();
