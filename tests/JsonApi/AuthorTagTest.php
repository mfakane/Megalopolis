<?php
declare(strict_types=1);

namespace Megalopolis\Tests\JsonApi;

use Megalopolis\Tests\Support\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthorTagTest extends TestCase
{
    public static function drivers(): iterable
    {
        yield 'sqlite' => ['sqlite'];
        yield 'mysql' => ['mysql'];
    }

    // MySQL counts the matches with found_rows(), which must not break the typed entry count.
    #[DataProvider('drivers')]
    public function testAuthorAndTagListsReturnTheirEntries(string $driver): void
    {
        $service = 'candidate-' . $driver;
        $entry = Http::json($service, '/index.php?path=' . rawurlencode('1.json'))->entries[0];

        $author = Http::json($service, '/index.php?path=' . rawurlencode('author/' . $entry->name . '.json'));
        self::assertSame($entry->name, $author->name);
        self::assertNotEmpty($author->entries);
        self::assertGreaterThanOrEqual(1, $author->pageCount);
        foreach ($author->entries as $i) {
            self::assertSame($entry->name, $i->name);
        }

        $tag = Http::json($service, '/index.php?path=' . rawurlencode('tag/' . $entry->tags[0] . '.json'));
        self::assertSame($entry->tags[0], $tag->tag);
        self::assertNotEmpty($tag->entries);
        self::assertGreaterThanOrEqual(1, $tag->pageCount);
        foreach ($tag->entries as $i) {
            self::assertContains($entry->tags[0], $i->tags);
        }
    }

    // Rows written without input normalization keep a bare "&", which the
    // normalized lookup ("&amp;") alone would never match.
    #[DataProvider('drivers')]
    public function testNamesWithBareAmpersandAreFound(string $driver): void
    {
        $service = 'candidate-' . $driver;
        $entries = Http::json($service, '/index.php?path=' . rawurlencode('1.json'))->entries;
        $names = array_values(array_filter(array_map(fn($x) => $x->name, $entries),
            fn($x) => preg_match('/&(?!amp;|lt;|gt;|quot;|#)/', $x) === 1));
        self::assertNotEmpty($names, 'Fixture must contain a bare ampersand name');

        $author = Http::json($service, '/index.php?path=' . rawurlencode('author/' . $names[0] . '.json'));
        self::assertSame($names[0], $author->name);
        self::assertNotEmpty($author->entries);
        foreach ($author->entries as $i) {
            self::assertSame($names[0], $i->name);
        }
    }
}
