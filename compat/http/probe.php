<?php
namespace Megalopolis;

// A narrow model adapter, not a second implementation of schema migration.
// App::openDB()/Thread::load() are always supplied by the checkout under test.
$subject = filter_input(INPUT_GET, 'subject', FILTER_VALIDATE_INT);
if (!$subject || $subject < 1 || $subject > 100) {
    http_response_code(400);
    exit('subject must be 1..100');
}
$handle = App::openDB();
$index = App::openDB(App::INDEX_DATABASE);
$rows = $handle->execute(function (\PDO $db) use ($subject): array {
    $rows = [];
    for ($ordinal = ($subject - 1) * 100 + 1; $ordinal <= $subject * 100; $ordinal++) {
        $thread = Thread::load($db, 1195084800 + $ordinal);
        if ($thread === null) {
            throw new \RuntimeException('Missing work ' . $ordinal);
        }
        $rows[] = [
            'id' => $thread->id, 'subject' => $thread->subject,
            'entrySubject' => $thread->entry->subject,
            'values' => array_merge([$thread->entry->title, $thread->entry->name,
                $thread->entry->summary, $thread->body, $thread->afterword], $thread->entry->tags),
            'pages' => $thread->entry->pageCount,
            'convertLineBreak' => $thread->convertLineBreak,
            'writingMode' => $thread->writingMode,
        ];
    }
    return $rows;
});
$index->close();
$handle->close();
header('Content-Type: application/json');
echo json_encode($rows, JSON_THROW_ON_ERROR);
