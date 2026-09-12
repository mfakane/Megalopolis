<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Tests\Support\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FixturesTest extends TestCase
{
    public static function files(): iterable
    {
        foreach (Fixtures::HASHES as $name => $hash) {
            yield $name => [$name, $hash];
        }
    }

    #[DataProvider('files')]
    public function testFrozenR46InputIsUnchanged(string $name, string $expected): void
    {
        $stream = gzopen(Fixtures::path($name), 'rb');
        $hash = hash_init('sha256');
        hash_update_stream($hash, $stream);
        gzclose($stream);
        self::assertSame($expected, hash_final($hash));
    }

    public function testDigestFramingDistinguishesNullEmptyAndFieldBoundaries(): void
    {
        self::assertSame(hash('sha256', 'N;S0:;S6:日本;'), Fixtures::digest([null, '', '日本']));
        self::assertNotSame(Fixtures::digest([null]), Fixtures::digest(['']));
        self::assertNotSame(Fixtures::digest(['ab', 'c']), Fixtures::digest(['a', 'bc']));
    }

    public function testManifestsCoverAllCombinationsAndOnlyIntendedBackendDifferences(): void
    {
        $sqlite = Fixtures::manifest('sqlite');
        $mysql = Fixtures::manifest('mysql');
        self::assertCount(10000, $sqlite);
        self::assertSame(array_keys($sqlite), array_keys($mysql));
        $different = 0;
        foreach (['sqlite' => $sqlite, 'mysql' => $mysql] as $driver => $rows) {
            $coverage = [];
            foreach ($rows as $id => $row) {
                self::assertSame(intdiv($id - 1195084801, 100) + 1, $row['subject']);
                $case = $row['text'] . '/' . $row['html'];
                $coverage[$case] = ($coverage[$case] ?? 0) + 1;
                if ($driver === 'sqlite' && $row['hash'] !== $mysql[$id]['hash']) {
                    $different++;
                }
            }
            self::assertCount(400, $coverage);
            self::assertSame([25], array_values(array_unique($coverage)));
        }
        self::assertSame(500, $different);
    }
}
