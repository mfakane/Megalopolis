<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Tests\Support\UpgradeFixtures;
use Megalopolis\Util;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UpgradeFixturesTest extends TestCase
{
    public function testFrozenInputsAndNonEmptyLinkedResponses(): void
    {
        foreach (UpgradeFixtures::HASHES as $name => $hash) {
            self::assertSame($hash, hash('sha256', UpgradeFixtures::bytes($name)));
        }
        $source = UpgradeFixtures::source();
        self::assertSame('5.2.5', $source['php']);
        self::assertSame(46, $source['revision']);
        self::assertSame('3.15.2', $source['databaseVersion']);
        self::assertCount(6, $source['works']);
        foreach ($source['works'] as $offset => $work) {
            self::assertSame(1200000001 + $offset, $work['entry']['id']);
            self::assertCount(2, $work['comments']);
            self::assertCount(2, $work['evaluations']);
            self::assertSame($work['comments'][0]['id'], $work['evaluations'][0]['id']);
            self::assertSame($work['evaluations'][0]['id'], $work['comments'][0]['evaluation']);
            self::assertNull($work['comments'][1]['evaluation']);
            self::assertSame(50, $work['entry']['points']);
            self::assertSame(3, $work['entry']['responseCount']);
            self::assertSame(7, $work['entry']['readCount']);
            self::assertNull($work['comments'][0]['mail']);
            self::assertSame('', $work['comments'][1]['mail']);
            self::assertSame('', $work['comments'][1]['body']);
        }
    }

    public static function oldKeys(): iterable
    {
        $source = UpgradeFixtures::source();
        yield 'admin' => [$source['adminHash'], 'upgrade-admin', Util::HASH_TYPE_MEGALOPOLIS1];
        foreach ($source['works'] as $offset => $work) {
            $i = $offset + 1;
            $type = match ($i) {
                3 => Util::HASH_TYPE_MEGALITH,
                5 => Util::HASH_TYPE_ANTHOLOGYS,
                default => Util::HASH_TYPE_MEGALOPOLIS1,
            };
            yield 'work-' . $i => [$work['hash'], $i === 5 ? 'legacy5' : '旧キー-' . $i, $type];
            foreach ($work['comments'] as $j => $comment) {
                yield 'comment-' . $i . '-' . $j => [$comment['hash'], '旧コメント-' . $i . '-' . ($j + 1),
                    Util::HASH_TYPE_MEGALOPOLIS1];
            }
        }
    }

    #[DataProvider('oldKeys')]
    public function testActualOldHashesAcceptOnlyTheCorrectKey(string $hash, string $key, string $type): void
    {
        self::assertSame($type, Util::hashEquals($hash, $key));
        self::assertFalse(Util::hashEquals($hash, 'wrong-key'));
        self::assertFalse(Util::hashEquals($hash, ''));
        self::assertFalse(Util::hashEquals($hash, false));
    }
}
