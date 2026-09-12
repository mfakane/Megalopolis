<?php
declare(strict_types=1);

namespace Megalopolis\Tests\JsonApi;

use Megalopolis\Tests\Support\Fixtures;
use Megalopolis\Tests\Support\Http;
use Megalopolis\Tests\Support\JsonContract;
use Megalopolis\Tests\Support\LegacyJsonEncoding;
use Megalopolis\Tests\Support\HtmlContract;
use Megalopolis\Tests\Support\LegacyHtml;
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
        $accepted = [];
        $htmlContract = isset($current->entry->id) && is_int($current->entry->id)
            && ($old->entry->id ?? null) === $current->entry->id
            ? LegacyHtml::forWork($driver, $current->entry->id, [
                'title' => $current->entry->title, 'name' => $current->entry->name,
                'summary' => $current->entry->summary, 'body' => $current->body,
                'afterword' => $current->afterword, 'tags' => $current->tags,
            ]) : null;
        // Always enforce the independent current expectation, even if both
        // implementations agree on the same historical content loss.
        if ($htmlContract !== null && (!is_string($current->formattedBody[0] ?? null)
            || !HtmlContract::equivalent($htmlContract['current'], $current->formattedBody[0]))) {
            $diff['$.formattedBody[0]'] = ['expected' => $htmlContract['current'],
                'actual' => $current->formattedBody[0] ?? null, 'reason' => 'reviewed-current-html-contract'];
        }
        foreach (LegacyJsonEncoding::rawFields($driver, $old, $current) as $field => $values) {
            self::assertSame($values['source'], $values['actual'], 'Frozen SQLite raw oracle: ' . $field);
            if (isset($diff[$field]) && LegacyJsonEncoding::acceptsRaw(
                $values['expected'], $values['actual'], $values['source'])) {
                $accepted[$field] = 'php52-json-supplementary-corruption';
                unset($diff[$field]);
            }
        }
        $unicodeSource = $driver === 'sqlite' && isset($current->entry->id)
            && is_int($current->entry->id) && $old->entry->id === $current->entry->id
            && (Fixtures::manifest('sqlite')[$current->entry->id]['text'] ?? '') === 'supplementary'
            ? Fixtures::sqlitePayload($current->entry->id) : null;
        if ($unicodeSource !== null) {
            foreach ($current->formattedBody as $html) {
                self::assertFalse(LegacyJsonEncoding::introducesCorruption($html, $unicodeSource['body']));
            }
            self::assertFalse(LegacyJsonEncoding::introducesCorruption(
                $current->formattedAfterword, $unicodeSource['afterword'] ?? ''));
        }
        foreach ($diff as $field => $values) {
            if (!preg_match('/^\$\.(formattedBody\[\d+\]|formattedAfterword)$/', $field)
                || !is_string($values['expected'] ?? null) || !is_string($values['actual'] ?? null)) {
                continue;
            }
            if ($field === '$.formattedBody[0]' && $htmlContract !== null
                && LegacyHtml::accepts($htmlContract, $values['expected'], $values['actual'])) {
                $accepted[$field] = $htmlContract['rule'];
                unset($diff[$field]);
            } elseif (HtmlContract::equivalent($values['expected'], $values['actual'])) {
                $accepted[$field] = 'equivalent-html-tree';
                unset($diff[$field]);
            } elseif ($unicodeSource !== null && LegacyJsonEncoding::acceptsHtml(
                $values['expected'], $values['actual'],
                $unicodeSource[$field === '$.formattedAfterword' ? 'afterword' : 'body'] ?? '')) {
                $accepted[$field] = 'php52-json-supplementary-corruption-in-html';
                unset($diff[$field]);
            }
        }
        if ($diff !== [] || $accepted !== []) {
            Http::artifact($driver . '-' . $route, ['route' => $route, 'differences' => $diff,
                'accepted' => $accepted,
                'r46' => Http::response('baseline-' . $driver),
                'current' => Http::response('candidate-' . $driver)]);
        }
        return [$old, $current, $diff];
    }
}
