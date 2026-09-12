<?php
// Mounted as r46's config.php; the original index.php loads the actual models.
require dirname(__FILE__) . '/upgrade-project.php';
function upgradeRequire($ok, $message)
{
    if (!$ok) throw new Exception($message);
}
try
{
    upgradeRequire(PHP_VERSION === '5.2.5' && PHP_SAPI === 'cli' && App::VERSION === 46,
        'Only the pinned r46 / PHP 5.2.5 generator is supported');
    $driver = getenv('COMPAT_DRIVER');
    upgradeRequire(in_array($driver, array('sqlite', 'mysql')), 'Unknown driver');
    upgradeRequire(!file_exists('/fixtures/' . $driver . '-source.json'), 'Refusing to overwrite an existing fixture');
    Configuration::$instance = new Configuration();
    $config = Configuration::$instance;
    $config->dataStore = $driver === 'sqlite' ? new SQLiteDataStore('/fixtures')
        : new MySQLDataStore('megalopolis_r46', array('mysql-generate', 3306), 'fixture', 'fixture');
    $db = App::openDB();
    $idb = App::openDB(App::INDEX_DATABASE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $idb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    upgradeRequire((int) $db->query('SELECT COUNT(*) FROM threadEntry')->fetchColumn() === 0, 'Database must be empty');
    $roles = array('preserve', 'edit', 'delete', 'comment', 'evaluate', 'admin');
    $source = array('php' => PHP_VERSION, 'revision' => App::VERSION,
        'databaseVersion' => $db->query($driver === 'sqlite' ? 'SELECT sqlite_version()' : 'SELECT VERSION()')->fetchColumn(),
        'adminHash' => Util::hash('upgrade-admin', 1, 'fedcba9876543210', 1000), 'works' => array());
    foreach ($roles as $offset => $role)
    {
        $i = $offset + 1;
        $thread = new Thread();
        $thread->id = 1200000000 + $i;
        $thread->subject = 1;
        $thread->entry->title = 'legacy-' . $role . ' 旧作品';
        $thread->entry->name = '旧作者' . $i;
        $thread->entry->summary = $i % 2 ? null : '';
        $thread->entry->mail = $i % 2 ? null : 'legacy@example.invalid';
        $thread->entry->link = 'https://example.invalid/old/' . $i;
        $thread->entry->host = '192.0.2.' . $i;
        $thread->entry->tags = array('旧分類', 'role-' . $role);
        $thread->entry->dateTime = 1200000000 + $i;
        $thread->entry->lastUpdate = 1200000100 + $i;
        $thread->entry->pageCount = 1;
        $thread->body = '<p>legacybody' . $role . ' 日本語 &amp; HTML</p>' . "\r\n" . '二行目';
        $thread->afterword = $i % 2 ? null : '';
        $thread->convertLineBreak = false;
        $thread->foreground = '#123456';
        $thread->background = null;
        $thread->backgroundImage = null;
        $thread->border = '#654321';
        $thread->writingMode = 1;
        $thread->hash = Util::hash('旧キー-' . $i, 1, '0123456789abcdef', 1000);
        if ($i === 3) $thread->hash = sha1('旧キー-3');
        if ($i === 5) $thread->hash = crypt('legacy5', 'ab');
        for ($j = 1; $j <= 2; $j++)
        {
            $comment = new Comment();
            $comment->id = 1200001000 + $i * 10 + $j;
            $comment->entryID = $thread->id;
            $comment->dateTime = $comment->id;
            $comment->name = $j === 1 ? '旧読者' . $i : '';
            $comment->mail = $j === 1 ? null : '';
            $comment->body = $j === 1 ? "旧コメント\r\n<b>感想</b> & \"引用\"" : '';
            $comment->host = '192.0.2.' . (20 + $j);
            $comment->hash = Util::hash('旧コメント-' . $i . '-' . $j, 1, 'aaaabbbbccccdddd', 1000);
            if ($j === 1)
            {
                $evaluation = new Evaluation();
                $evaluation->entryID = $thread->id;
                $evaluation->id = $comment->id;
                $evaluation->dateTime = $comment->dateTime;
                $evaluation->point = 30;
                $evaluation->host = $comment->host;
                $comment->evaluation = $evaluation;
                $thread->evaluations[$evaluation->id] = $evaluation;
            }
            $thread->comments[$comment->id] = $comment;
        }
        $evaluation = new Evaluation();
        $evaluation->entryID = $thread->id;
        $evaluation->id = 1200002000 + $i;
        $evaluation->dateTime = $evaluation->id;
        $evaluation->point = 20;
        $evaluation->host = '192.0.2.30';
        $thread->nonCommentEvaluations[$evaluation->id] = $thread->evaluations[$evaluation->id] = $evaluation;
        $thread->entry->updateCount($thread);
        $thread->entry->readCount = 7;
        $thread->entry->responseLastUpdate = $evaluation->dateTime;
        // Record expectations BEFORE insertion, not from the database readback.
        $source['works'][] = upgradeProject($thread);
        $thread->save($db, false);
        foreach ($thread->comments as $comment) $comment->save($db);
        $evaluation->save($db);
        SearchIndex::register($idb, $thread);
    }
    $collection = new stdClass();
    $collection->id = 1;
    $collection->lastUpdate = 1200000106;
    Util::saveToTable($db, $collection, Board::$subjectSchema, App::SUBJECT_TABLE);
    upgradeRequire((int) $db->query('SELECT COUNT(*) FROM comment')->fetchColumn() === 12, 'Expected 12 comments');
    upgradeRequire((int) $db->query('SELECT COUNT(*) FROM evaluation')->fetchColumn() === 12, 'Expected 12 evaluations');
    upgradeRequire(file_put_contents('/fixtures/' . $driver . '-source.json', json_encode($source)) !== false, 'Cannot write source manifest');
    App::closeDB($idb);
    App::closeDB($db);
    echo 'Generated ' . $driver . ": six works, twelve comments, twelve evaluations, three password formats\n";
    exit(0);
}
catch (Exception $error)
{
    fwrite(STDERR, $error->getMessage() . "\n" . $error->getTraceAsString() . "\n");
    exit(1);
}
