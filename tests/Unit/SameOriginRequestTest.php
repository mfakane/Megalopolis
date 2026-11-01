<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Configuration;
use Megalopolis\Util;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SameOriginRequestTest extends TestCase
{
    private array $server;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/req/Model/Board.php';
        Configuration::$instance = new Configuration();
        $this->server = $_SERVER;
        $_SERVER = ['HTTP_HOST' => 'example.jp', 'SERVER_NAME' => 'example.jp', 'REMOTE_ADDR' => '203.0.113.5'];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    public static function requests(): iterable
    {
        // Sec-Fetch-Site is decided by the browser and wins over Origin/Referer.
        yield 'fetch metadata same-origin' => [['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_ORIGIN' => 'https://evil.example'], true];
        yield 'fetch metadata cross-site' => [['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'https://example.jp'], false];
        yield 'fetch metadata same-site subdomain' => [['HTTP_SEC_FETCH_SITE' => 'same-site', 'HTTP_ORIGIN' => 'https://sub.example.jp'], false];
        // Older browsers: compare the host of Origin, then Referer.
        yield 'origin same host' => [['HTTP_ORIGIN' => 'https://example.jp'], true];
        yield 'origin other port, scheme behind a TLS proxy' => [['HTTP_ORIGIN' => 'https://EXAMPLE.jp:8443'], true];
        yield 'origin other host' => [['HTTP_ORIGIN' => 'https://evil.example'], false];
        yield 'origin host as prefix' => [['HTTP_ORIGIN' => 'https://example.jp.evil.example'], false];
        yield 'origin null' => [['HTTP_ORIGIN' => 'null', 'HTTP_REFERER' => 'https://example.jp/'], false];
        yield 'referer same host' => [['HTTP_REFERER' => 'http://example.jp/megalopolis/1/2'], true];
        // The previous check let these through because strpos() returned false and false != 0 is false.
        yield 'referer other host' => [['HTTP_REFERER' => 'http://evil.example/?http://example.jp/'], false];
        yield 'no source' => [[], false];
        yield 'fetch metadata none falls back to origin' => [['HTTP_SEC_FETCH_SITE' => 'none', 'HTTP_ORIGIN' => 'https://evil.example'], false];
    }

    #[DataProvider('requests')]
    public function testSource(array $headers, bool $expected): void
    {
        $_SERVER = $headers + $_SERVER;
        self::assertSame($expected, Util::isSameOriginRequest());
    }

    public function testForwardedHostIsTrustedOnlyFromProxies(): void
    {
        $_SERVER = ['HTTP_HOST' => 'app:8080', 'SERVER_NAME' => 'app', 'HTTP_X_FORWARDED_HOST' => 'example.jp', 'HTTP_ORIGIN' => 'https://example.jp'];

        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
        self::assertTrue(Util::isSameOriginRequest(), 'private proxy is trusted by default');

        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';
        self::assertFalse(Util::isSameOriginRequest(), 'a public client must not choose the host');
    }
}
