<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Auth;
use Megalopolis\Configuration;
use PHPUnit\Framework\TestCase;

/**
 * A session whose fingerprint changes is logged out (Auth::useSession), so the
 * fingerprint must stay the same for every request of one browser.
 */
final class AuthSessionTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/req/Model/Board.php';
        Configuration::$instance = new Configuration();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';
        $_SERVER['HTTP_USER_AGENT'] = 'Browser/1.0';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'ja';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    // IPv6 temporary addresses, mobile networks and proxies with several exits
    // change the address between requests; each change used to log out.
    public function testFingerprintDoesNotDependOnTheClientAddress(): void
    {
        $before = $this->fingerprint();
        $_SERVER['REMOTE_ADDR'] = '2001:db8::1234';
        self::assertSame($before, $this->fingerprint());
    }

    public function testFingerprintDiffersForAnotherBrowser(): void
    {
        $before = $this->fingerprint();
        $_SERVER['HTTP_USER_AGENT'] = 'Another/1.0';
        self::assertNotSame($before, $this->fingerprint());
    }

    private function fingerprint(): string
    {
        return (new \ReflectionMethod(Auth::class, 'createFingerprint'))->invoke(null);
    }
}
