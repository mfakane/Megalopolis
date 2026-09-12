<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\SQLiteDataStore;
use Megalopolis\Thread;
use Megalopolis\ThreadEntry;
use PDO;
use PHPUnit\Framework\TestCase;

final class ReferenceContractTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        foreach (['Core/DataStore', 'Model/ThreadEntry', 'Model/Thread'] as $name) {
            require_once dirname(__DIR__, 2) . '/req/' . $name . '.php';
        }
    }

    public function testThreadRetainsTheEntryVariableNotOnlyTheOriginalObject(): void
    {
        $entry = new ThreadEntry(1);
        $entry->subject = 3;
        $thread = new Thread($entry);
        self::assertSame(1, $thread->id);
        self::assertSame(3, $thread->subject);

        $entry = new ThreadEntry(2);
        $entry->subject = 7;
        self::assertSame($entry, $thread->entry);
        self::assertSame(2, $thread->id);
        self::assertSame(7, $thread->subject);

        $thread->entry = new ThreadEntry(9);
        self::assertSame(9, $entry->id, 'Reassignment is visible in both directions');
    }

    public function testRegisteredHandleKeepsTheCallersReference(): void
    {
        $store = new class extends SQLiteDataStore {
            public function __construct() {}
            public function register(PDO &$db): void { $this->registerHandle($db, 'test'); }
            public function registered(): ?PDO { return $this->getHandleByName('test'); }
        };
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE original (id INTEGER)');
        $store->register($db);
        self::assertSame($db, $store->registered());
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE replacement (id INTEGER)');
        self::assertSame($db, $store->registered());
        self::assertSame(['replacement'], $store->registered()->query(
            "SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN));
    }
}
