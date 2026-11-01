<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Util;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BrowserTypeTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/req/Model/Board.php';
    }

    public static function agents(): iterable
    {
        yield 'iPhone Safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', Util::BROWSER_TYPE_IPHONE];
        // In-app browsers (LINE, X, ...) on iPhone omit "Safari"; they used to be treated as feature phones.
        yield 'iPhone in-app browser' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Line/14.0.0', Util::BROWSER_TYPE_IPHONE];
        yield 'Android phone' => ['Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36', Util::BROWSER_TYPE_ANDROIDMOBILE];
        yield 'Android tablet' => ['Mozilla/5.0 (Linux; Android 15; Pixel Tablet) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36', Util::BROWSER_TYPE_ANDROID];
        yield 'Huawei Android phone' => ['Mozilla/5.0 (Linux; Android 10; HUAWEI P30) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36', Util::BROWSER_TYPE_ANDROIDMOBILE];
        yield 'docomo feature phone' => ['DoCoMo/2.0 P903i(c100;TB;W24H12)', Util::BROWSER_TYPE_MOBILE];
        yield 'au feature phone' => ['KDDI-SA31 UP.Browser/6.2.0.7.3.129 (GUI) MMP/2.0', Util::BROWSER_TYPE_MOBILE];
        yield 'Nintendo 3DS' => ['Mozilla/5.0 (Nintendo 3DS; U; ; ja) Version/1.7412.JP', Util::BROWSER_TYPE_3DS];
        yield 'desktop Chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36', Util::BROWSER_TYPE_UNKNOWN];
        yield 'desktop Firefox' => ['Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0', Util::BROWSER_TYPE_UNKNOWN];
        yield 'no user agent' => ['', Util::BROWSER_TYPE_UNKNOWN];
    }

    #[DataProvider('agents')]
    public function testDetect(string $ua, string $expected): void
    {
        self::assertSame($expected, Util::detectBrowserType($ua));
    }
}
