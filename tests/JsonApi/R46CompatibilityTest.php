<?php
declare(strict_types=1);

namespace Megalopolis\Tests\JsonApi;

use Megalopolis\Tests\Support\Fixtures;
use Megalopolis\Tests\Support\Http;
use Megalopolis\Tests\Support\JsonContract;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class R46CompatibilityTest extends TestCase
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
    public function testEverySubjectReturnsTheSameOrderedEntries(string $driver, int $subject): void
    {
        [$old, $current, $diff] = $this->responses($driver, $subject . '.json');
        foreach ([$old, $current] as $json) {
            self::assertSame($subject, $json->subject);
            self::assertSame(100, $json->subjectCount);
            self::assertCount(100, $json->entries);
            $ids = array_map(fn($entry) => $entry->id, $json->entries);
            sort($ids); // Set coverage only; the JSON diff retains the original order.
            self::assertSame(range(1195084801 + ($subject - 1) * 100, 1195084800 + $subject * 100), $ids);
        }
        self::assertSame([], array_keys($diff), $driver . ' subject ' . $subject . ' (see test-results)');
    }

    public static function works(): iterable
    {
        $total = getenv('COMPAT_FULL') === '1' ? 10000 : 400;
        foreach (['sqlite', 'mysql'] as $driver) {
            for ($ordinal = 1; $ordinal <= $total; $ordinal++) {
                yield $driver . '/' . $ordinal => [$driver, $ordinal];
            }
            if ($total === 400) {
                yield $driver . '/last' => [$driver, 10000];
            }
        }
    }

    #[DataProvider('works')]
    public function testWorkJsonPreservesTheR46Contract(string $driver, int $ordinal): void
    {
        $id = 1195084800 + $ordinal;
        $subject = intdiv($ordinal - 1, 100) + 1;
        [$old, $current, $diff] = $this->responses($driver, $subject . '/' . $id . '.json');
        foreach ([$old, $current] as $json) {
            self::assertSame($id, $json->entry->id);
            self::assertSame($subject, $json->entry->subject);
            self::assertSame(1, $json->entry->readCount, 'Fresh visitor increments exactly once');
        }
        // r46/PHP 5.2 itself misencodes some supplementary Unicode. The candidate
        // must preserve the actual DB bytes, not imitate that historical defect.
        self::assertSame(Fixtures::manifest($driver)[$id]['hash'], Fixtures::digest(array_merge([
            $current->entry->title, $current->entry->name, $current->entry->summary,
            $current->body, $current->afterword,
        ], $current->tags)), 'Current JSON raw bytes must match the pre-insertion oracle');
        self::assertSame([], array_keys($diff), $driver . ' ' . $id . ' (see test-results)');
    }

    private function responses(string $driver, string $route): array
    {
        $path = '/index.php?path=' . rawurlencode($route);
        $old = Http::json('baseline-' . $driver, $path);
        $current = Http::json('candidate-' . $driver, $path);
        $diff = JsonContract::differences($old, $current);
        if ($diff !== []) {
            Http::artifact($driver . '-' . $route, ['route' => $route, 'differences' => $diff,
                'r46' => Http::response('baseline-' . $driver),
                'current' => Http::response('candidate-' . $driver)]);
        }
        return [$old, $current, $diff];
    }
}
