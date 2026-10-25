<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Configuration;
use Megalopolis\Util;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RemoteAddressTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/req/Model/Board.php';
        Configuration::$instance = new Configuration();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    public static function requests(): iterable
    {
        yield 'direct client cannot spoof the header' => [null, '203.0.113.5', '198.51.100.1', '203.0.113.5'];
        yield 'local reverse proxy is trusted by default' => [null, '127.0.0.1', '198.51.100.1', '198.51.100.1'];
        yield 'private network proxy is trusted by default' => [null, '10.0.0.2', '198.51.100.1', '198.51.100.1'];
        yield 'client cannot prepend a fake address' => [null, '127.0.0.1', '1.2.3.4, 198.51.100.1', '198.51.100.1'];
        yield 'chained trusted proxies are skipped' => [null, '127.0.0.1', '198.51.100.1, 10.0.0.3', '198.51.100.1'];
        yield 'no header' => [null, '203.0.113.5', null, '203.0.113.5'];
        yield 'configured proxy' => [['203.0.113.*'], '203.0.113.9', '198.51.100.1', '198.51.100.1'];
        yield 'configured list excludes private ranges' => [['203.0.113.*'], '127.0.0.1', '198.51.100.1', '127.0.0.1'];
        yield 'invalid header values are ignored' => [null, '127.0.0.1', 'unknown, <script>', '127.0.0.1'];
    }

    #[DataProvider('requests')]
    public function testForwardedForIsUsedOnlyFromTrustedProxies(?array $proxies, string $remote, ?string $forwarded, string $expected): void
    {
        Configuration::$instance->trustedProxies = $proxies;
        $_SERVER['REMOTE_ADDR'] = $remote;
        if ($forwarded !== null) {
            $_SERVER['HTTP_X_FORWARDED_FOR'] = $forwarded;
        }

        self::assertSame($expected, Util::getRemoteAddress());
    }
}
