<?php
// PHP 5.2-compatible, read-only model projection shared by the two runtimes.
// No SQL schema reproduction and no authentication shortcut.
function upgradeProject($thread)
{
    if (!$thread) return null;
    $entry = array();
    foreach (array('id', 'subject', 'dateTime', 'lastUpdate', 'pageCount', 'points',
        'responseCount', 'commentCount', 'evaluationCount', 'readCount', 'responseLastUpdate') as $key)
        $entry[$key] = (int) $thread->entry->$key;
    foreach (array('title', 'name', 'summary', 'mail', 'link', 'host') as $key)
        $entry[$key] = $thread->entry->$key;
    $entry['tags'] = $thread->entry->tags;
    $result = array('entry' => $entry);
    foreach (array('body', 'afterword', 'foreground', 'background', 'backgroundImage', 'border', 'hash') as $key)
        $result[$key] = $thread->$key;
    $result['convertLineBreak'] = (bool) $thread->convertLineBreak;
    $result['writingMode'] = (int) $thread->writingMode;
    $result['comments'] = array();
    $comments = $thread->comments;
    ksort($comments, SORT_NUMERIC);
    foreach ($comments as $comment)
    {
        $row = array('id' => (int) $comment->id, 'entryID' => (int) $comment->entryID,
            'dateTime' => (int) $comment->dateTime,
            'evaluation' => $comment->evaluation ? (int) $comment->evaluation->id : null);
        foreach (array('name', 'mail', 'body', 'host', 'hash') as $key)
            $row[$key] = $comment->$key;
        $result['comments'][] = $row;
    }
    $result['evaluations'] = array();
    $evaluations = $thread->evaluations;
    ksort($evaluations, SORT_NUMERIC);
    foreach ($evaluations as $evaluation)
        $result['evaluations'][] = array('id' => (int) $evaluation->id,
            'entryID' => (int) $evaluation->entryID, 'point' => (int) $evaluation->point,
            'host' => $evaluation->host, 'dateTime' => (int) $evaluation->dateTime);
    return $result;
}
