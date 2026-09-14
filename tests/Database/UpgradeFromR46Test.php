<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Database;

use Megalopolis\Tests\Support\Fixtures;
use Megalopolis\Tests\Support\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UpgradeFromR46Test extends TestCase
{
    public static function subjects(): iterable
    {
        foreach (['sqlite', 'mysql'] as $driver) {
            for ($subject = 1; $subject <= 100; $subject++) {
                yield $driver . '/' . $subject => [$driver, $subject];
            }
        }
    }

    #[DataProvider('subjects')]
    public function testCurrentModelsPreserveEveryR46PayloadAfterOpenAndReopen(string $driver, int $subject): void
    {
        $manifest = Fixtures::manifest($driver);
        // Fresh PHP requests exercise opening/migration twice, without requiring
        // current physical table names or schema versions to remain r46-shaped.
        for ($pass = 1; $pass <= 2; $pass++) {
            $rows = Http::json('candidate-' . $driver, '/__compat/database?subject=' . $subject);
            self::assertIsArray($rows);
            self::assertCount(100, $rows);
            foreach ($rows as $offset => $row) {
                $ordinal = ($subject - 1) * 100 + $offset + 1;
                $id = 1195084800 + $ordinal;
                $label = "$driver/$id open=$pass";
                self::assertSame($id, $row->id, $label);
                self::assertSame($subject, $row->subject, $label);
                self::assertSame($subject, $row->entrySubject, $label);
                self::assertCount(8, $row->values, $label);
                self::assertSame($manifest[$id]['hash'], Fixtures::digest($row->values), $label);
                self::assertSame($manifest[$id]['bytes'], strlen($row->values[3]), $label);
                self::assertSame($manifest[$id]['pages'], $row->pages, $label);
                self::assertSame($ordinal % 2 === 0, $row->convertLineBreak, $label);
                self::assertSame($ordinal % 3, $row->writingMode, $label);
            }
        }
    }
}
