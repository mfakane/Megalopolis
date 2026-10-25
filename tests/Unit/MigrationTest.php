<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Configuration;
use Megalopolis\Meta;
use Megalopolis\Migration;
use Megalopolis\SQLiteDataStore;
use Megalopolis\Tests\Support\Fixtures;
use PDO;
use PHPUnit\Framework\TestCase;

/** The schema migration gate on a copy of the frozen r46 SQLite database. */
final class MigrationTest extends TestCase
{
    private string $directory;
    private string|false $errorLog;

    public static function setUpBeforeClass(): void
    {
        foreach (['Core/Constant', 'Core/ApplicationException', 'Core/Util', 'Core/Configuration', 'Core/DataStore',
            'Model/Meta', 'Model/Migration', 'Model/Board', 'Model/ThreadEntry', 'Model/Thread', 'Model/Comment',
            'Model/Evaluation', 'Model/SearchIndex', 'Model/SearchIndex/Classic', 'Model/SearchIndex/SQLite',
            'Model/SearchIndex/MySQL'] as $name) {
            require_once dirname(__DIR__, 2) . '/req/' . $name . '.php';
        }
    }

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/megalopolis-migration-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
        copy('compress.zlib://' . Fixtures::path('data.sqlite'), $this->directory . '/data.sqlite');
        Configuration::$instance = new Configuration();
        Migration::$lockWait = 1;
        // The failure test logs the exception on purpose.
        $this->errorLog = ini_set('error_log', '/dev/null');
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string)$this->errorLog);
        array_map('unlink', glob($this->directory . '/*') ?: []);
        rmdir($this->directory);
    }

    /** A new request: nothing cached from the previous one. */
    private function open(): array
    {
        Meta::$meta = null;
        $store = new SQLiteDataStore($this->directory);
        Configuration::$instance->dataStore = $store;
        return [$store, $store->open()];
    }

    private function raw(): PDO
    {
        return new PDO('sqlite:' . $this->directory . '/data.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private function hasIndex(string $name): bool
    {
        $st = $this->raw()->prepare("select count(*) from sqlite_master where type = 'index' and name = ?");
        $st->execute([$name]);
        return (int)$st->fetchColumn() === 1;
    }

    private function meta(string $name): ?string
    {
        $st = $this->raw()->prepare('select value from meta where name = ?');
        $st->execute([$name]);
        $value = $st->fetchColumn();
        return $value === false ? null : $value;
    }

    public function testFirstOpenMigratesOnceAndLaterOpensSkipTheMigration(): void
    {
        [$store] = $this->open();
        self::assertFalse($store->isMigrationPending());
        self::assertSame(Migration::latestVersion(), $this->meta(Migration::VERSION_META_NAME));
        self::assertSame('4', $this->meta('threadEntry'));
        self::assertTrue($this->hasIndex('threadEntryDateTimeIndex'));

        // Up to date: a later request does not run (and so does not repair) the migration.
        $this->raw()->exec('drop index threadEntryDateTimeIndex');
        [$store] = $this->open();
        self::assertFalse($store->isMigrationPending());
        self::assertFalse($this->hasIndex('threadEntryDateTimeIndex'));
    }

    public function testWhileAnotherRequestMigratesTheSiteIsPending(): void
    {
        $lock = fopen($this->directory . '/migration.lock', 'c');
        self::assertTrue(flock($lock, LOCK_EX));
        try {
            [$store] = $this->open();
            self::assertTrue($store->isMigrationPending());
            self::assertNull($this->meta(Migration::VERSION_META_NAME));
            self::assertFalse($this->hasIndex('threadEntryDateTimeIndex'));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        [$store] = $this->open();
        self::assertFalse($store->isMigrationPending());
    }

    public function testFailedMigrationIsRolledBackAndWaitsForTheAdministrator(): void
    {
        // "tags" cannot be created while a view has its name; earlier steps then roll back.
        $this->raw()->exec('drop table tags; create view tags as select 1 as tag');

        [$store, $db] = $this->open();
        self::assertTrue($store->isMigrationPending());
        self::assertNotNull(Migration::getError($db));
        self::assertNull($this->meta(Migration::VERSION_META_NAME));
        self::assertSame('3', $this->meta('threadEntry'));
        self::assertFalse($this->hasIndex('threadEntryDateTimeIndex'));
        self::assertFalse($db->inTransaction());

        // Not retried automatically, even once the cause is gone.
        $this->raw()->exec('drop view tags');
        [$store] = $this->open();
        self::assertTrue($store->isMigrationPending());

        [$store, $db] = $this->open();
        self::assertTrue($store->ensureMigrated($db, true));
        self::assertFalse($store->isMigrationPending());
        self::assertNull($this->meta(Migration::ERROR_META_NAME));
        self::assertSame(Migration::latestVersion(), $this->meta(Migration::VERSION_META_NAME));
        self::assertTrue($this->hasIndex('threadEntryDateTimeIndex'));
        self::assertGreaterThan(0, (int)$this->raw()->query('select count(*) from tags')->fetchColumn());
    }
}
