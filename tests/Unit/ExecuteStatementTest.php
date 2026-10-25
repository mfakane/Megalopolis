<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Configuration;
use Megalopolis\SQLiteDataStore;
use Megalopolis\Util;
use PDO;
use PHPUnit\Framework\TestCase;

final class ExecuteStatementTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/req/Model/Board.php';
        require_once dirname(__DIR__, 2) . '/req/Core/DataStore.php';
        Configuration::$instance = new Configuration();
        Configuration::$instance->dataStore = new SQLiteDataStore(sys_get_temp_dir());
        // PHP 8 PDO throws by default; callers passing $throw = false rely on it being caught.
        $this->db = new PDO('sqlite::memory:');
        $this->db->exec('create table t (a integer unique)');
        $this->db->exec('insert into t values (1)');
    }

    public function testFailureIsReportedInsteadOfThrownWhenIgnored(): void
    {
        $st = $this->db->prepare('insert into t values (?)');

        self::assertFalse(Util::executeStatement($st, [1], false));
        self::assertTrue(Util::executeStatement($st, [2], false));
    }

    public function testFailureStillThrowsByDefault(): void
    {
        $this->expectException(\PDOException::class);
        Util::executeStatement($this->db->prepare('insert into t values (?)'), [1]);
    }

    public function testMigrationStatementsMayFail(): void
    {
        Util::executeIgnoringErrors($this->db, 'alter table t add column a integer');
        Util::executeIgnoringErrors($this->db, 'drop trigger missingTrigger');
        self::assertSame(1, (int)$this->db->query('select count(*) from t')->fetchColumn());
    }
}
