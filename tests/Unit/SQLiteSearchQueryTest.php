<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Configuration;
use Megalopolis\SQLiteSearchIndex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SQLiteSearchQueryTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        foreach (['Board', 'ThreadEntry', 'Thread', 'SearchIndex', 'SearchIndex/SQLite'] as $name) {
            require_once dirname(__DIR__, 2) . '/req/Model/' . $name . '.php';
        }
    }

    protected function setUp(): void
    {
        Configuration::$instance = new Configuration();
    }

    public static function queries(): iterable
    {
        // The index stores bigrams per segment between symbols, in order and with repeats.
        yield 'word is a phrase of its grams' => [['plain'], true, '"pl la ai in"'];
        yield 'repeated grams keep their order' => [['作品00005'], true, '"作品 品0 00 00 00 05"'];
        yield 'single character is a prefix' => [['a'], true, 'a*'];
        yield 'segments are separate conditions' => [['HTML:plain'], true, '"ht tm ml" "pl la ai in"'];
        yield 'short segment is a prefix' => [['文字:japanese'], true, '"文字" "ja ap pa an ne es se"'];
        yield 'excluded word' => [['plain', '-html'], true, '"pl la ai in" NOT "ht tm ml"'];
        yield 'excluded segments are excluded together' => [['plain', '-HTML:a'], true, '"pl la ai in" NOT ("ht tm ml" AND a*)'];
        yield 'standard syntax excludes with minus' => [['plain', '-html'], false, '"pl la ai in" -"ht tm ml"'];
        yield 'exclusion alone cannot be searched' => [['-html'], true, ''];
    }

    #[DataProvider('queries')]
    public function testBuildsMatchQueryPerSegment(array $query, bool $enhanced, string $expected): void
    {
        self::assertSame($expected, (new SQLiteSearchIndex())->buildMatchQuery($query, $enhanced));
    }

    public function testIndexedWordsKeepRepeatedGramsInOrder(): void
    {
        $index = new SQLiteSearchIndex();
        self::assertSame(['作品', '品0', '00', '00', '00', '05', '5', 'pl', 'la', 'ai', 'in', 'n'],
            $index->getWords([['unique' => false], '作品00005 plain']));
    }

    public function testIndexesBuiltBeforeThePhraseFormatKeepDroppingRepeatedGrams(): void
    {
        // Until regenerated, an old index only matches the old (deduplicated) phrases.
        $index = new SQLiteSearchIndex();
        (new \ReflectionProperty($index, 'phraseFormat'))->setValue($index, false);

        self::assertSame('"作品 品0 00 05"', $index->buildMatchQuery(['作品00005'], true));
        self::assertSame('"le eg ga ac cy yb bo od dy yd de el et te"', $index->buildMatchQuery(['legacybodydelete'], true));
    }
}
