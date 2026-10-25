<?php
declare(strict_types=1);

namespace Megalopolis\Tests\JsonApi;

use Megalopolis\Tests\Support\Browser;
use Megalopolis\Tests\Support\Drivers;
use Megalopolis\Tests\Support\SearchOracle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Searches the r46 index as shipped (not regenerated). An old index may also
 * return works that only contain the search term's grams, but must never miss
 * one, and the page count must describe exactly the listed works.
 */
final class SearchCountTest extends TestCase
{
    public const array QUERIES = [
        'unique title' => ['title' => '作品00001'],
        'title prefix' => ['title' => '作品0001'],
        'many titles' => ['title' => '作品00'],
        'latin title' => ['title' => 'Café'],
        'repeated grams' => ['title' => 'ABC abc'],
        'name' => ['name' => '作者001'],
        'tag' => ['tag' => 'plain'],
        'tag after a tag with the same grams' => ['tag' => 'HTML:plain'],
        'tag exclusion' => ['tag' => 'japanese -plain'],
        'title and tag' => ['title' => '作品00', 'tag' => 'plain'],
    ];

    public static function queries(): iterable
    {
        foreach (array_keys(Drivers::ALL) as $driver) {
            foreach (self::QUERIES as $name => $query) {
                yield $driver . '/' . $name => [$driver, $query];
            }
        }
    }

    #[DataProvider('queries')]
    public function testOldIndexFindsEveryMatchingWork(string $driver, array $query): void
    {
        static $works = [];
        $works[$driver] ??= SearchOracle::works(SearchOracle::candidate($driver));
        $expected = SearchOracle::expected($works[$driver], $query);
        self::assertNotEmpty($expected, 'The fixture must contain matching works');

        $found = SearchOracle::found(new Browser('candidate-' . $driver), $query);
        self::assertSame([], array_values(array_diff($expected, $found)), 'Missed works');
    }
}
