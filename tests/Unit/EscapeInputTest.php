<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Configuration;
use Megalopolis\Util;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EscapeInputTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/req/Model/Board.php';
        Configuration::$instance = new Configuration();
    }

    public static function inputs(): iterable
    {
        // A lone supplementary character is also valid Shift_JIS-looking bytes.
        yield 'lone supplementary character' => ['𠮷', '𠮷'];
        yield 'emoji' => ['😀', '😀'];
        yield 'mixed' => ['題名😀𠮷', '題名😀𠮷'];
        yield 'legacy Shift_JIS input' => [mb_convert_encoding('日本語', 'SJIS-win', 'UTF-8'), '日本語'];
        yield 'legacy EUC-JP input' => [mb_convert_encoding('日本語テキスト', 'eucJP-win', 'UTF-8'), '日本語テキスト'];
    }

    #[DataProvider('inputs')]
    public function testValidUtf8IsKeptAndLegacyEncodingsAreConverted(string $input, string $expected): void
    {
        self::assertSame($expected, Util::escapeInput($input));
    }

    #[DataProvider('inputs')]
    public function testPathsKeepValidUtf8(string $input, string $expected): void
    {
        $normalize = new \ReflectionMethod(Util::class, 'normalizePath');

        self::assertSame($expected, $normalize->invoke(null, $input));
    }
}
