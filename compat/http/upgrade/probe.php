<?php
// Only installed by the isolated test-image config. A read-only model adapter.
require dirname(__FILE__) . '/project.php';
function upgradeClass($name)
{
    return class_exists('Megalopolis\\' . $name) ? 'Megalopolis\\' . $name : $name;
}
function upgradeRead($db)
{
    $ids = isset($_GET['id']) ? array((int) $_GET['id']) : range(1200000001, 1200000006);
    $works = $orphans = array();
    foreach ($ids as $id)
    {
        $thread = call_user_func(array(upgradeClass('Thread'), 'load'), $db, $id);
        $works[] = upgradeProject($thread);
        if (!$thread)
            $orphans[] = array('id' => $id,
                'comments' => count(call_user_func(array(upgradeClass('Comment'), 'getCommentsFromEntryID'), $db, $id)),
                'evaluations' => count(call_user_func(array(upgradeClass('Evaluation'), 'getEvaluationsFromEntryID'), $db, $id)));
    }
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    return array('works' => $works, 'orphans' => $orphans, 'php' => PHP_VERSION,
        'driver' => $driver,
        'databaseVersion' => $db->query($driver === 'sqlite' ? 'SELECT sqlite_version()' : 'SELECT VERSION()')->fetchColumn());
}
if (isset($_GET['id']) && (!is_string($_GET['id']) || !preg_match('/^[1-9][0-9]{0,9}$/', $_GET['id'])))
{
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid ID');
}
$app = upgradeClass('App');
$handle = call_user_func(array($app, 'openDB'));
$index = call_user_func(array($app, 'openDB'), constant($app . '::INDEX_DATABASE'));
$snapshot = $handle instanceof PDO ? upgradeRead($handle) : $handle->execute('upgradeRead');
foreach (array($index, $handle) as $handle)
    if ($handle instanceof PDO) call_user_func(array($app, 'closeDB'), $handle);
    else $handle->close();
header('Content-Type: application/json');
echo json_encode($snapshot);
exit;
