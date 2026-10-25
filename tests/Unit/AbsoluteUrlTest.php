<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Configuration;
use Megalopolis\Util;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class AbsoluteUrlTest extends TestCase
{
    public static function linkTypes(): array
    {
        return [
            'path' => [Configuration::LINK_PATH, 'http://example.test/sub/index.php/util/reindex?p=1&c=2'],
            'query' => [Configuration::LINK_QUERY, 'http://example.test/sub/index.php?path=util/reindex&p=1&c=2'],
            'rewrite' => [Configuration::LINK_REWRITE, 'http://example.test/sub/util/reindex?p=1&c=2'],
        ];
    }

    // getAbsoluteUrl() caches per process, so each link type needs a fresh one.
    #[DataProvider('linkTypes')]
    #[RunInSeparateProcess]
    public function testKeepsQueryOfPathUnderSubdirectory(int $linkType, string $expected): void
    {
        require_once dirname(__DIR__, 2) . '/req/Model/Board.php';
        $configuration = new Configuration();
        $configuration->linkType = $linkType;
        Configuration::$instance = $configuration;
        $_SERVER['SCRIPT_NAME'] = '/sub/index.php';
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['SERVER_PORT'] = '80';
        unset($_SERVER['HTTPS']);

        self::assertSame($expected, Util::getAbsoluteUrl('util/reindex?p=1&c=2'));
    }
}
