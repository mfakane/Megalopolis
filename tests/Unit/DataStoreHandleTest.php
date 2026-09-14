<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\ApplicationException;
use Megalopolis\DataStore;
use Megalopolis\DataStoreHandle;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DataStoreHandleTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/req/Core/DataStore.php';
    }

    /** In-memory connections without App bootstrap; transaction methods are inherited unchanged. */
    private function connection(): array
    {
        $store = new class extends DataStore {
            public function open(string $database = 'data'): PDO
            {
                return new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            }

            public function close(PDO &$db, bool $vacuum = false): void
            {
                // The test owns the connection until it has inspected the rows.
            }

            public function getTables(PDO $db): array
            {
                return $db->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
            }

            public function createFullTextTableIfNotExists(PDO $db, array $schema, string $name, string $indexSuffix = 'Index'): bool
            {
                throw new \LogicException('Full-text schemas are outside this transaction test');
            }
        };
        $db = $store->open();
        $db->exec('CREATE TABLE records (value TEXT PRIMARY KEY)');
        $db->exec("INSERT INTO records VALUES ('original')");
        return [new DataStoreHandle($store, $db), $db, $store];
    }

    private function rows(PDO $db): array
    {
        return $db->query('SELECT value FROM records ORDER BY value')->fetchAll(PDO::FETCH_COLUMN);
    }

    private function thrown(callable $action): \Throwable
    {
        try {
            $action();
        } catch (\Throwable $error) {
            return $error;
        }
        self::fail('Expected the transaction to propagate an exception');
    }

    public static function applicationCodes(): iterable
    {
        foreach ([400, 401, 403, 404, 500] as $code) yield (string)$code => [$code];
    }

    #[DataProvider('applicationCodes')]
    public function testApplicationExceptionSurvivesRollbackWithItsIdentityAndData(int $code): void
    {
        [$handle, $db] = $this->connection();
        $cause = new \RuntimeException('original cause');
        $expected = new ApplicationException('application failure', $code, $cause);
        $expected->data = ['field' => 'password', 'detail' => ['旧キー', null]];

        $actual = $this->thrown(fn() => $handle->withTransaction(function (PDO $db) use ($expected): void {
            $db->exec("UPDATE records SET value = 'changed'");
            $db->exec("INSERT INTO records VALUES ('partial')");
            throw $expected;
        }));

        self::assertSame(['original'], $this->rows($db), 'Real writes must be rolled back');
        self::assertFalse($db->inTransaction());
        self::assertSame($expected, $actual, 'Do not reconstruct or wrap application exceptions');
        self::assertSame($code, $actual->httpCode);
        self::assertSame(['field' => 'password', 'detail' => ['旧キー', null]], $actual->data);
        self::assertSame($cause, $actual->getPrevious());
        self::assertSame('committed', $handle->withTransaction(function (PDO $db): string {
            $db->exec("INSERT INTO records VALUES ('after-error')");
            return 'committed';
        }));
        self::assertSame(['after-error', 'original'], $this->rows($db), 'Handle remains usable after rollback');
    }

    public function testCaughtNestedFailureRollsBackOnlyInnerWrites(): void
    {
        [$handle, $db] = $this->connection();
        $expected = new ApplicationException('inner rejection', 403);
        $actual = $handle->withTransaction(function (PDO $db) use ($handle, $expected): \Throwable {
            $db->exec("INSERT INTO records VALUES ('outer-before')");
            $actual = $this->thrown(fn() => $handle->withTransaction(function (PDO $db) use ($expected): void {
                $db->exec("UPDATE records SET value = 'changed' WHERE value = 'original'");
                $db->exec("INSERT INTO records VALUES ('inner')");
                throw $expected;
            }, true));
            self::assertTrue($db->inTransaction(), 'Outer transaction must still be open');
            self::assertSame(['original', 'outer-before'], $this->rows($db));
            $db->exec("INSERT INTO records VALUES ('outer-after')");
            return $actual;
        });

        self::assertSame(['original', 'outer-after', 'outer-before'], $this->rows($db));
        self::assertFalse($db->inTransaction());
        self::assertSame($expected, $actual);
    }

    public static function combinations(): iterable
    {
        yield 'two handles sharing one connection' => [true];
        yield 'independent connections and stores' => [false];
    }

    #[DataProvider('combinations')]
    public function testCombinedFailureRollsBackBothWritesAndPropagatesTheOriginalException(bool $shared): void
    {
        [$handle, $db, $store] = $this->connection();
        if ($shared) {
            $otherDb = $db;
            $other = new DataStoreHandle($store, $db);
        } else {
            [$other, $otherDb] = $this->connection();
        }
        $expected = new ApplicationException('missing work', 404);
        $expected->data = ['id' => 123];
        $actual = $this->thrown(fn() => $handle->withTransactionCombo($other,
            function (PDO $db, PDO $otherDb) use ($expected): void {
                $db->exec("INSERT INTO records VALUES ('first-write')");
                $otherDb->exec("INSERT INTO records VALUES ('second-write')");
                throw $expected;
            }));

        self::assertSame(['original'], $this->rows($db));
        self::assertSame(['original'], $this->rows($otherDb));
        self::assertFalse($db->inTransaction());
        self::assertFalse($otherDb->inTransaction());
        self::assertSame($expected, $actual);
        self::assertSame(404, $actual->httpCode);
        self::assertSame(['id' => 123], $actual->data);
        $handle->withTransactionCombo($other, function (PDO $db, PDO $otherDb): void {
            $db->exec("INSERT INTO records VALUES ('first-commit')");
            $otherDb->exec("INSERT INTO records VALUES ('second-commit')");
        });
        self::assertSame($shared ? ['first-commit', 'original', 'second-commit'] : ['first-commit', 'original'], $this->rows($db));
        self::assertSame($shared ? ['first-commit', 'original', 'second-commit'] : ['original', 'second-commit'], $this->rows($otherDb));
    }

    public static function unexpectedErrors(): iterable
    {
        yield 'runtime exception' => [\RuntimeException::class];
        yield 'PHP type error' => [\TypeError::class];
    }

    #[DataProvider('unexpectedErrors')]
    public function testUnexpectedThrowableStillBecomes500AfterRollback(string $class): void
    {
        [$handle, $db] = $this->connection();
        $cause = new $class('unexpected failure');
        $actual = $this->thrown(fn() => $handle->withTransaction(function (PDO $db) use ($cause): void {
            $db->exec("INSERT INTO records VALUES ('partial')");
            throw $cause;
        }));

        self::assertSame(['original'], $this->rows($db));
        self::assertFalse($db->inTransaction());
        self::assertInstanceOf(ApplicationException::class, $actual);
        self::assertSame(500, $actual->httpCode);
        self::assertSame('unexpected failure', $actual->getMessage());
        self::assertSame($cause, $actual->getPrevious());
    }

    public function testRealDatabaseFailureStillBecomes500AndRollsBackEarlierWrites(): void
    {
        [$handle, $db] = $this->connection();
        $actual = $this->thrown(fn() => $handle->withTransaction(function (PDO $db): void {
            $db->exec("INSERT INTO records VALUES ('partial')");
            $db->exec("INSERT INTO records VALUES ('original')"); // Real primary-key violation.
        }));

        self::assertSame(['original'], $this->rows($db));
        self::assertFalse($db->inTransaction());
        self::assertInstanceOf(ApplicationException::class, $actual);
        self::assertSame(500, $actual->httpCode);
        self::assertInstanceOf(\PDOException::class, $actual->getPrevious());
    }
}
