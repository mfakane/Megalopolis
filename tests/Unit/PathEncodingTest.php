<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Configuration;
use Megalopolis\Visualizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class PathEncodingTest extends TestCase
{
    private const NAME = "日本 A&B+C%41/D#?\t";

    public static function linkTypes(): array
    {
        return [
            // Apache decodes the path, then PHP decodes ?path= again.
            'rewrite' => [Configuration::LINK_REWRITE, 2],
            'path' => [Configuration::LINK_PATH, 1],
            'query' => [Configuration::LINK_QUERY, 1],
        ];
    }

    // Util::getSuffix() caches per process, so each link type needs a fresh one.
    #[DataProvider('linkTypes')]
    #[RunInSeparateProcess]
    public function testNameSurvivesTheDecodingOfEachLinkType(int $linkType, int $decodes): void
    {
        require_once dirname(__DIR__, 2) . '/req/Model/Board.php';
        $configuration = new Configuration();
        $configuration->linkType = $linkType;
        Configuration::$instance = $configuration;

        $href = Visualizer::currentHref('author', self::NAME, 'random');
        self::assertStringStartsWith('author/', $href);
        self::assertStringEndsWith('/random', $href);
        $segment = substr($href, strlen('author/'), -strlen('/random'));
        self::assertDoesNotMatchRegularExpression('/[\x00-\x20&#?]/', $segment);

        // Path decoding keeps "+"; query decoding turns it into a space.
        $decoded = $segment;
        for ($i = 1; $i <= $decodes; $i++) {
            $decoded = $i === $decodes && $linkType !== Configuration::LINK_PATH
                ? urldecode($decoded)
                : rawurldecode($decoded);
        }
        self::assertSame(self::NAME, $decoded);
    }
}
