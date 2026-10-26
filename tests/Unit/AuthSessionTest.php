<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Auth;
use Megalopolis\Configuration;
use PHPUnit\Framework\TestCase;

/**
 * A session whose fingerprint changes is logged out (Auth::useSession), so the
 * fingerprint must stay the same for every request of one browser. The
 * administrator's login is kept apart from the session, in a signed cookie.
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
        $_COOKIE = [];
        (new \ReflectionProperty(Auth::class, 'adminKey'))->setValue(null, null);
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

    // The administrator's login is a signed cookie, so it outlives the PHP
    // session (whose files the server may delete after 24 minutes).
    public function testAdminLoginIsTheSignedCookie(): void
    {
        $this->login(time());
        self::assertTrue(Auth::hasSession(true));

        $value = $_COOKIE[$this->cookie()];
        $value[-1] = $value[-1] === '0' ? '1' : '0';
        $_COOKIE[$this->cookie()] = $value;
        self::assertFalse(Auth::hasSession(true), 'tampered');

        // The key comes from config.php; editing it (the password) logs out.
        $this->login(time());
        (new \ReflectionProperty(Auth::class, 'adminKey'))->setValue(null, str_repeat('0', 64));
        self::assertFalse(Auth::hasSession(true), 'config.php was changed');
        Configuration::$instance->adminHash = null;
        self::assertFalse(Auth::hasSession(true), 'no administrator');
    }

    public function testAdminLoginExpires(): void
    {
        // Until the browser closes, but at most a day without being used.
        $this->login(time() - 86400 + 60);
        self::assertTrue(Auth::hasSession(true));
        $this->login(time() - 86400);
        self::assertFalse(Auth::hasSession(true));

        Configuration::$instance->adminLoginDays = 7;
        $this->login(time() - 6 * 86400);
        self::assertTrue(Auth::hasSession(true));
        $this->login(time() - 7 * 86400);
        self::assertFalse(Auth::hasSession(true));
    }

    public function testAdminTokenStaysValidAfterTheSessionIsGone(): void
    {
        $this->login(time());
        $token = Auth::createToken();
        $_SESSION = [];
        $_POST['token'] = $token;
        try {
            self::assertTrue(Auth::ensureToken());
        } finally {
            unset($_POST['token']);
        }
    }

    private function login(int $issued): void
    {
        Configuration::$instance->adminHash ??= 'hash';
        $_COOKIE[$this->cookie()] = (new \ReflectionMethod(Auth::class, 'createAdminLogin'))
            ->invoke(null, $issued, str_repeat('ab', 16));
    }

    private function cookie(): string
    {
        return 'MEGALOPOLIS_ADMIN_' . basename(dirname(__DIR__, 2));
    }

    private function fingerprint(): string
    {
        return (new \ReflectionMethod(Auth::class, 'createFingerprint'))->invoke(null);
    }
}
