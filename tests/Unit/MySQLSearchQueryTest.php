<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Configuration;
use Megalopolis\MySQLSearchIndex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MySQLSearchQueryTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/req/Model/Board.php';
        foreach (['SearchIndex', 'SearchIndex/SQLite', 'SearchIndex/MySQL'] as $name) {
            require_once dirname(__DIR__, 2) . '/req/Model/' . $name . '.php';
        }
    }

    public static function queries(): iterable
    {
        // The index stores 4-grams per segment between symbols, padded with "_".
        yield 'long word is a phrase of its grams' => [['plain'], '+"plai lain"'];
        yield 'short word matches a padded gram' => [['abc'], '+abc_'];
        yield 'segments are separate conditions' => [['HTML:plain'], '+"html" +"plai lain"'];
        yield 'short segment is kept' => [['分類:ABC'], '+分類__ +abc_'];
        yield 'mixed segments' => [['文字:japanese'], '+文字__ +"japa apan pane anes nese"'];
        yield 'excluded word' => [['-plain'], '-"plai lain"'];
        yield 'excluded segments are excluded together' => [['-HTML:plain'], '-(+"html" +"plai lain")'];
        yield 'several words' => [['plain', '-abc'], '+"plai lain" -abc_'];
        yield 'symbols only' => [[':-'], ''];
    }

    #[DataProvider('queries')]
    public function testBuildsBooleanQueryPerSegment(array $query, string $expected): void
    {
        $configuration = new Configuration();
        $configuration->mysqlSearchNgramLength = 4;
        $configuration->mysqlSearchUseHeadMatching = false;
        Configuration::$instance = $configuration;

        self::assertSame($expected, (new MySQLSearchIndex())->buildBooleanQuery($query));
    }

    public function testPadsShortGramsByCharacters(): void
    {
        $configuration = new Configuration();
        $configuration->mysqlSearchNgramLength = 4;
        Configuration::$instance = $configuration;

        self::assertSame(['文字__', 'abc_', 'japa'], (new MySQLSearchIndex())->padWords(['文字', 'abc', 'japa']));
    }
}
