<?php
// Installed as r46's config.php. Its original index.php and req/ bootstrap us.
// Keep this file valid PHP 5.2: no namespaces, closures or short array syntax.
function compatCheck($condition, $message)
{
    if (!$condition)
        throw new Exception($message);
}

function compatError($severity, $message, $file, $line)
{
    if (error_reporting() & $severity)
        throw new Exception($message . ' at ' . $file . ':' . $line);
}

function compatStore($driver)
{
    if ($driver == 'sqlite')
        return new SQLiteDataStore('/fixtures');
    return new MySQLDataStore(
        getenv('COMPAT_MYSQL_DATABASE'),
        array(getenv('COMPAT_MYSQL_HOST'), 3306),
        getenv('COMPAT_MYSQL_USER'),
        getenv('COMPAT_MYSQL_PASSWORD')
    );
}

function compatDatabase($driver)
{
    Configuration::$instance = new Configuration();
    Meta::$meta = null;
    SearchIndex::$instance = null;
    Board::$latestSubject = null;
    $store = compatStore($driver);
    Configuration::$instance->dataStore = $store;

    // These r46 entry points create the schema, including version metadata.
    $db = App::openDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $idb = App::openDB(App::INDEX_DATABASE);
    $session = new SessionStore();
    $session->open('', 'php52-fixture');
    $session->write('fixture', 'fixture|s:6:"日本";');
    compatCheck($session->read('fixture') === 'fixture|s:6:"日本";', $driver . ': session round trip');
    $session->close();

    $expected = array(
        'meta', 'subject', 'threadEntry', 'threadEvaluation', 'threadTag',
        'thread', 'threadStyle', 'threadPassword', 'comment', 'evaluation',
        'author', 'tags', 'sessionStore'
    );
    $tables = $store->getTables($db);
    foreach ($expected as $table)
        compatCheck(in_array($table, $tables), $driver . ': missing table ' . $table);
    compatCheck(in_array('searchIndex2', $store->getTables($idb)), $driver . ': missing search index');
    compatCheck(Meta::get($db, Meta::DATA_VERSION) === '1', $driver . ': wrong data version');

    // Use the real model's write path, including its related tables and index.
    $thread = new Thread();
    $thread->id = 1195084800;
    $thread->subject = 1;
    $thread->entry->title = 'PHP 5.2.5 互換テスト';
    $thread->entry->name = 'fixture作者';
    $thread->entry->tags = array('互換性');
    $thread->entry->dateTime = 1195084800;
    $thread->entry->lastUpdate = 1195084800;
    $thread->entry->pageCount = 1;
    $thread->body = '日本語の旧DB fixture本文';
    $thread->hash = Util::hash('fixture');
    $thread->save($db);
    SearchIndex::register($idb, $thread);

    App::closeDB($idb);
    App::closeDB($db);
    // A fresh store models the next request. r46's per-store table-name cache
    // retains mixed-case names after creation; do not reuse it across requests.
    Configuration::$instance->dataStore = compatStore($driver);
    Meta::$meta = null;
    // Reopen to check persistence, using SQL as an independent read oracle.
    $db = App::openDB();
    $st = $db->prepare('SELECT e.title, e.name, t.body FROM threadEntry e JOIN thread t ON t.id = e.id WHERE e.id = ?');
    compatCheck($st !== false && $st->execute(array(1195084800)), $driver . ': fixture query failed');
    $row = $st->fetch(PDO::FETCH_ASSOC);
    compatCheck($row && $row['title'] === $thread->entry->title
        && $row['name'] === $thread->entry->name && $row['body'] === $thread->body,
        $driver . ': persisted Japanese fixture differs');
    $st = null;
    $idb = App::openDB(App::INDEX_DATABASE);
    compatCheck((int) $idb->query('SELECT COUNT(*) FROM searchIndex2 WHERE docid = 1195084800')->fetchColumn() === 1,
        $driver . ': search index fixture missing or duplicated');
    if ($driver == 'sqlite')
    {
        compatCheck($db->query('PRAGMA integrity_check')->fetchColumn() === 'ok', 'SQLite data integrity');
        compatCheck($idb->query('PRAGMA integrity_check')->fetchColumn() === 'ok', 'SQLite search integrity');
    }
    $version = $db->query($driver == 'sqlite' ? 'SELECT sqlite_version()' : 'SELECT VERSION()')->fetchColumn();
    App::closeDB($idb);
    App::closeDB($db);
    echo 'PASS ' . $driver . ' ' . $version . ': r46 schema (13 tables + search index), model write, Japanese readback' . "\n";
}

try
{
    set_error_handler('compatError');
    $mode = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : 'all';
    compatCheck(PHP_SAPI === 'cli' && PHP_VERSION === '5.2.5', 'Requires PHP 5.2.5 CLI');
    compatCheck(App::VERSION === 46, 'Requires Megalopolis r46');
    foreach (array('PDO', 'pdo_sqlite', 'pdo_mysql', 'mbstring', 'hash', 'pcre') as $extension)
        compatCheck(extension_loaded($extension), 'Missing extension: ' . $extension);
    compatCheck(mb_strlen('日本語', 'UTF-8') === 3, 'mbstring UTF-8 length');
    compatCheck(mb_convert_encoding(mb_convert_encoding('日本語', 'SJIS-win', 'UTF-8'), 'UTF-8', 'SJIS-win') === '日本語',
        'mbstring Japanese conversion');
    $memory = new PDO('sqlite::memory:');
    compatCheck((int) $memory->query('SELECT 20 + 22')->fetchColumn() === 42, 'SQLite PDO query');
    $memory = null;
    if ($mode === 'generate')
    {
        require '/opt/php52/fixtures/generate.php';
        compatGenerateMain($_SERVER['argv']);
        exit(0);
    }
    echo "PASS PHP 5.2.5 CLI, PDO SQLite/MySQL, mbstring; SQLite SELECT = 42\n";
    compatCheck(in_array($mode, array('all', 'sqlite', 'mysql')), 'Usage: php index.php [all|sqlite|mysql]');
    foreach ($mode == 'all' ? array('sqlite', 'mysql') : array($mode) as $driver)
        compatDatabase($driver);
    echo "PASS all requested compatibility checks\n";
    exit(0);
}
catch (Exception $ex)
{
    fwrite(STDERR, 'FAIL: ' . $ex->getMessage() . "\n" . $ex->getTraceAsString() . "\n");
    exit(1);
}
