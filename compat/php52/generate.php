<?php
require dirname(__FILE__) . '/corpus.php';

define('LARGE_TOTAL', 10000);
define('LARGE_PER_SUBJECT', 100);
define('LARGE_FIRST_ID', 1195084801);
define('LARGE_HEADER', "id\tsubject\ttext_case\thtml_case\tbody_bytes\tpages\tpayload_sha256\n");

function largeManifest($driver)
{
    return '/fixtures/large-' . $driver . '.tsv';
}

// Hash byte strings with length framing; distinguish SQL NULL from empty text.
// Field order: title, name, summary, body, afterword, then the 3 ordered tags.
function largeDigest($values)
{
    $hash = hash_init('sha256');
    foreach ($values as $value)
        hash_update($hash, is_null($value) ? 'N;' : 'S' . strlen($value) . ':' . $value . ';');
    return hash_final($hash);
}

function largeOpen($driver)
{
    Configuration::$instance = new Configuration();
    Configuration::$instance->subjectSplitting = LARGE_PER_SUBJECT;
    Configuration::$instance->dataStore = compatStore($driver);
    $db = App::openDB();
    $idb = App::openDB(App::INDEX_DATABASE);
    Util::createTableIfNotExists($db, SessionStore::$sessionStoreSchema, App::SESSION_STORE_TABLE,
        array('sessionStoreLastUpdateIndex' => array('lastUpdate')));
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $idb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return array($db, $idb);
}

function largeInitialize($driver)
{
    $path = largeManifest($driver);
    compatCheck(!file_exists($path) && !file_exists($path . '.partial'),
        'Generation already started; use a fresh Compose project/volumes. Existing data is preserved.');
    list($db, $idb) = largeOpen($driver);
    foreach (array('subject', 'threadEntry', 'thread', 'threadStyle', 'threadPassword',
        'threadEvaluation', 'threadTag', 'author', 'tags', 'comment', 'evaluation', 'sessionStore') as $table)
        compatCheck((int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() === 0,
            'Refusing to add large fixtures to nonempty table ' . $table . '; use a fresh Compose project.');
    compatCheck((int) $idb->query('SELECT COUNT(*) FROM searchIndex2')->fetchColumn() === 0, 'Search index must be empty');
    $manifest = fopen($path . '.partial', 'x');
    compatCheck($manifest !== false, 'Cannot reserve manifest');
    compatCheck(fwrite($manifest, LARGE_HEADER) === strlen(LARGE_HEADER), 'Cannot write manifest header');
    fclose($manifest);
    echo 'Generating ' . $driver . ': 100 subjects x 100 works; '
        . ($driver == 'sqlite' ? 'including supplementary Unicode' : 'BMP only (legacy MySQL utf8)') . "\n";
}

function largeBatch($driver, $subject)
{
    compatCheck($subject >= 1 && $subject <= 100, 'Subject must be 1..100');
    $path = largeManifest($driver) . '.partial';
    compatCheck(is_file($path), 'Run generate init first');
    list($db, $idb) = largeOpen($driver);
    $start = ($subject - 1) * LARGE_PER_SUBJECT;
    compatCheck((int) $db->query('SELECT COUNT(*) FROM threadEntry')->fetchColumn() === $start,
        'Out-of-order or repeated batch; existing works will not be overwritten');
    compatCheck((int) $idb->query('SELECT COUNT(*) FROM searchIndex2')->fetchColumn() === $start,
        'Incomplete previous search batch; use fresh volumes');
    $db->beginTransaction();
    if ($driver == 'sqlite')
        $idb->beginTransaction();
    $lines = '';
    for ($ordinal = $start + 1; $ordinal <= $start + LARGE_PER_SUBJECT; $ordinal++)
    {
        list($thread, $textCase, $htmlCase) = largeThread($ordinal, $driver == 'sqlite');
        compatCheck(mb_strlen($thread->entry->title) <= 255, 'Title exceeds r46 schema');
        $digest = largeDigest(array_merge(array($thread->entry->title, $thread->entry->name,
            $thread->entry->summary, $thread->body, $thread->afterword), $thread->entry->tags));
        $thread->save($db, false);
        SearchIndex::register($idb, $thread, false);
        $lines .= implode("\t", array($thread->id, $subject, $textCase, $htmlCase,
            strlen($thread->body), $thread->entry->pageCount, $digest)) . "\n";
    }
    // Populate subjects through r46's data store, with reproducible timestamps.
    $collection = new stdClass();
    $collection->id = $subject;
    $collection->lastUpdate = $thread->entry->lastUpdate;
    Util::saveToTable($db, $collection, Board::$subjectSchema, App::SUBJECT_TABLE);
    if ($driver == 'sqlite')
        $idb->commit();
    $db->commit();
    $manifest = fopen($path, 'a');
    compatCheck(fwrite($manifest, $lines) === strlen($lines), 'Incomplete manifest write');
    fclose($manifest);
    if ($subject % 10 == 0)
        echo $driver . ': ' . ($subject * LARGE_PER_SUBJECT) . '/' . LARGE_TOTAL . " works\n";
}

function largeVerify($driver)
{
    $path = largeManifest($driver);
    $input = is_file($path . '.partial') ? $path . '.partial' : $path;
    compatCheck(is_file($input), 'No generation manifest found');
    $manifest = fopen($input, 'r');
    compatCheck(fgets($manifest) === LARGE_HEADER, 'Unexpected manifest header');
    list($db, $idb) = largeOpen($driver);
    foreach (array('threadEntry', 'thread', 'threadStyle', 'threadPassword', 'threadEvaluation') as $table)
        compatCheck((int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() === LARGE_TOTAL, 'Wrong count: ' . $table);
    foreach (array('subject' => 100, 'author' => 200, 'tags' => 60, 'threadTag' => 30000,
        'comment' => 0, 'evaluation' => 0, 'sessionStore' => 0) as $table => $count)
        compatCheck((int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() === $count, 'Wrong count: ' . $table);
    compatCheck(Meta::get($db, Meta::DATA_VERSION) === '1', 'Wrong r46 data version');
    $counts = $db->query('SELECT subject, COUNT(*) AS n FROM threadEntry GROUP BY subject ORDER BY subject');
    for ($subject = 1; $subject <= 100; $subject++)
    {
        $row = $counts->fetch(PDO::FETCH_ASSOC);
        compatCheck($row && (int) $row['subject'] === $subject && (int) $row['n'] === 100,
            'Expected exactly 100 works in subject ' . $subject);
    }
    compatCheck($counts->fetch() === false, 'Extra subjects');
    $counts = null;
    foreach (array(
        'SELECT a.name FROM author a LEFT JOIN threadEntry e ON e.name = a.name GROUP BY a.name, a.threadCount HAVING a.threadCount <> COUNT(e.id)',
        'SELECT a.tag FROM tags a LEFT JOIN threadTag t ON t.tag = a.tag GROUP BY a.tag, a.threadCount HAVING a.threadCount <> COUNT(t.id)'
    ) as $sql)
        compatCheck($db->query($sql)->fetch() === false, 'Trigger-maintained counts differ');

    $query = $db->prepare('SELECT e.id, e.subject, e.title, e.name, e.summary, e.pageCount, e.size,
        t.body, t.afterword, t.subject AS bodySubject, s.convertLineBreak, s.writingMode
        FROM threadEntry e JOIN thread t ON t.id = e.id JOIN threadStyle s ON s.id = e.id
        WHERE e.subject = ? ORDER BY e.id');
    $tagQuery = $db->prepare('SELECT id, tag FROM threadTag WHERE id BETWEEN ? AND ? ORDER BY id, position');
    $verified = 0;
    $coverage = array();
    $minBytes = PHP_INT_MAX;
    $maxBytes = 0;
    $nullSummaries = $emptySummaries = $threePageWorks = 0;
    for ($subject = 1; $subject <= 100; $subject++)
    {
        $first = LARGE_FIRST_ID + ($subject - 1) * LARGE_PER_SUBJECT;
        $tagQuery->execute(array($first, $first + 99));
        $tags = array();
        while ($row = $tagQuery->fetch(PDO::FETCH_ASSOC))
            $tags[$row['id']][] = $row['tag'];
        $query->execute(array($subject));
        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            $expected = fgetcsv($manifest, 4096, "\t");
            compatCheck(is_array($expected) && count($expected) === 7, 'Missing manifest row');
            $id = LARGE_FIRST_ID + $verified;
            compatCheck((int) $row['id'] === $id && (int) $expected[0] === $id
                && (int) $expected[1] === $subject && (int) $row['bodySubject'] === $subject, 'ID/subject mismatch');
            compatCheck(isset($tags[$id]) && count($tags[$id]) === 3, 'Missing ordered tags for ' . $id);
            $digest = largeDigest(array_merge(array($row['title'], $row['name'], $row['summary'],
                $row['body'], $row['afterword']), $tags[$id]));
            compatCheck($digest === $expected[6], 'Stored bytes differ from pre-insert hash at ' . $id);
            $bytes = strlen($row['body']);
            compatCheck($bytes === (int) $expected[4] && (int) $row['pageCount'] === (int) $expected[5], 'Body length/page mismatch');
            $matches = array();
            compatCheck((int) $row['pageCount'] === preg_match_all(Thread::REGEX_SPLIT_PAGE, $row['body'], $matches) + 1,
                'Page delimiter mismatch');
            $ordinal = $verified + 1;
            compatCheck((bool) $row['convertLineBreak'] === ($ordinal % 2 == 0)
                && (int) $row['writingMode'] === $ordinal % 3, 'Style mismatch');
            $minBytes = min($minBytes, $bytes);
            $maxBytes = max($maxBytes, $bytes);
            if (is_null($row['summary'])) $nullSummaries++;
            if ($row['summary'] === '') $emptySummaries++;
            if ((int) $row['pageCount'] === 3) $threePageWorks++;
            $case = $expected[2] . '/' . $expected[3];
            if (!isset($coverage[$case])) $coverage[$case] = 0;
            $coverage[$case]++;
            $verified++;
        }
    }
    compatCheck($verified === LARGE_TOTAL && fgetcsv($manifest, 4096, "\t") === false, 'Manifest/DB total differs');
    fclose($manifest);
    $query = $tagQuery = null;
    foreach (array_keys(largeTextCases($driver == 'sqlite')) as $text)
        foreach (array_keys(largeHtmlCases()) as $html)
            compatCheck(isset($coverage[$text . '/' . $html]) && $coverage[$text . '/' . $html] === 25, 'Missing corpus combination');
    compatCheck(count($coverage) === 400 && $nullSummaries === 3333 && $emptySummaries === 3334
        && $threePageWorks === 500, 'Corpus distribution differs');
    $search = $idb->query('SELECT docid FROM searchIndex2 ORDER BY docid');
    for ($id = LARGE_FIRST_ID; $id < LARGE_FIRST_ID + LARGE_TOTAL; $id++)
        compatCheck((int) $search->fetchColumn() === $id, 'Missing/duplicate search document ' . $id);
    compatCheck($search->fetchColumn() === false, 'Extra search documents');
    $search = null;
    if ($driver == 'sqlite')
    {
        compatCheck($db->query('PRAGMA integrity_check')->fetchColumn() === 'ok', 'SQLite data integrity');
        compatCheck($idb->query('PRAGMA integrity_check')->fetchColumn() === 'ok', 'SQLite search integrity');
    }
    if ($input !== $path)
        compatCheck(rename($input, $path), 'Cannot finalize manifest');
    $summary = 'generator=1' . "\nphp=" . PHP_VERSION . "\nr46=dff38a8ab25b758e8bb7ea1a621cf33983e9e54e\n"
        . 'driver=' . $driver . "\nunicode=" . ($driver == 'sqlite' ? 'full' : 'BMP')
        . "\nsubjects=100\nworks_per_subject=100\nworks=10000\nauthors=200\ntags=60\nsearch_documents=10000\n"
        . "text_cases=20\nhtml_cases=20\ncombinations=400\nworks_per_combination=25\nthree_page_works=500\n"
        . 'min_body_bytes=' . $minBytes . "\nmax_body_bytes=" . $maxBytes . "\n"
        . 'manifest_sha256=' . hash_file('sha256', $path) . "\nverified_payloads=" . $verified . "\n";
    compatCheck(file_put_contents('/fixtures/large-' . $driver . '-summary.txt', $summary) === strlen($summary), 'Cannot write summary');
    echo 'PASS ' . $driver . ': 100 x 100 works; all 10000 payload hashes, 400 cases, tags, author counts, pages and search IDs verified' . "\n";
}

function largeWorker($driver, $stage, $subject = null)
{
    // r46's create_function() allocations last for the request. A fresh CLI
    // process per subject bounds memory and resets its static/table caches.
    $command = '/opt/php52/bin/php index.php generate ' . escapeshellarg($driver) . ' ' . escapeshellarg($stage);
    if (!is_null($subject)) $command .= ' ' . (int) $subject;
    passthru($command, $status);
    compatCheck($status === 0, 'Generation worker failed: ' . $driver . ' ' . $stage . ' ' . $subject);
}

function compatGenerateMain($args)
{
    $driver = isset($args[2]) ? $args[2] : 'all';
    $stage = isset($args[3]) ? $args[3] : 'run';
    compatCheck(in_array($driver, array('all', 'sqlite', 'mysql')), 'Expected driver: all|sqlite|mysql');
    compatCheck(in_array($stage, array('run', 'init', 'batch', 'verify')), 'Expected stage: run|init|batch|verify');
    if ($stage == 'run')
    {
        foreach ($driver == 'all' ? array('sqlite', 'mysql') : array($driver) as $item)
        {
            largeWorker($item, 'init');
            for ($subject = 1; $subject <= 100; $subject++) largeWorker($item, 'batch', $subject);
            largeWorker($item, 'verify');
        }
    }
    else
    {
        compatCheck($driver !== 'all', 'Worker stages require a single driver');
        if ($stage == 'init') largeInitialize($driver);
        if ($stage == 'verify') largeVerify($driver);
        if ($stage == 'batch')
        {
            compatCheck(isset($args[4]) && preg_match('/^[0-9]+$/D', $args[4]), 'Expected subject number');
            largeBatch($driver, (int) $args[4]);
        }
    }
}
