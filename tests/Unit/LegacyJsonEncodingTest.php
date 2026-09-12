<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Tests\Support\Fixtures;
use Megalopolis\Tests\Support\LegacyJsonEncoding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LegacyJsonEncodingTest extends TestCase
{
    public function testOnlyTheObservedPHP52CorruptionIsAcceptedAgainstOriginalBytes(): void
    {
        self::assertTrue(LegacyJsonEncoding::acceptsRaw('𐮷野家 𙸽 𐮟 😀', '𠮷野家 𩸽 𠮟 😀', '𠮷野家 𩸽 𠮟 😀'));
    }

    public static function regressions(): iterable
    {
        yield 'current also corrupts input' => ['𐮷', '𐮷', '𠮷'];
        yield 'current truncates text' => ['𐮷', '𠮷', '𠮷野家'];
        yield 'unrelated old difference' => ['吉野家', '𠮷野家', '𠮷野家'];
        yield 'different original glyph' => ['𐮷', '𠮷', '吉'];
        yield 'missing field' => [null, '𠮷', '𠮷'];
        yield 'null versus empty' => ['', null, null];
        yield 'no corruption' => ['😀', '😀', '😀'];
        yield 'line endings changed' => ["𐮷\r\n", "𠮷\n", "𠮷\r\n"];
        yield 'emoji lost' => ['𐮷', '𠮷', '𠮷😀'];
    }

    #[DataProvider('regressions')]
    public function testUnrelatedChangesStillFail(mixed $old, mixed $current, mixed $source): void
    {
        self::assertFalse(LegacyJsonEncoding::acceptsRaw($old, $current, $source));
    }

    public function testOracleContainsActualR46BytesAndOrderedTags(): void
    {
        $payload = Fixtures::sqlitePayload(1195084820);
        self::assertSame('作品00020 [supplementary/plain] 𠮷野家 𩸽 𠮟る 😀🧪 👩‍💻 🏳️‍🌈 𝄞 𐐷', $payload['title']);
        self::assertSame(['文字:supplementary', 'HTML:plain', '分類:𠮷野家 𩸽 𠮟る 😀🧪 👩‍💻 🏳️‍🌈 𝄞 𐐷'], $payload['tags']);
        self::assertSame(Fixtures::manifest('sqlite')[1195084820]['hash'], Fixtures::digest(array_merge([
            $payload['title'], $payload['name'], $payload['summary'], $payload['body'], $payload['afterword'],
        ], $payload['tags'])));
    }

    public function testFormattedHtmlExceptionDoesNotHideOtherChanges(): void
    {
        self::assertTrue(LegacyJsonEncoding::acceptsHtml('<p>𐮷 &quot;x&quot;</p>', '<p>𠮷 "x"</p>', '𠮷 "x"'));
        self::assertFalse(LegacyJsonEncoding::acceptsHtml('<p>𐮷</p>', '<p>𠮷</p>', '吉'));
        self::assertFalse(LegacyJsonEncoding::acceptsHtml('<p>𐮷</p>', '<p>𐮷</p>', '𠮷'));
        self::assertFalse(LegacyJsonEncoding::acceptsHtml('<p>𐮷 x</p>', '<p>𠮷 y</p>', '𠮷 x'));
        self::assertFalse(LegacyJsonEncoding::acceptsHtml('<p>𐮷</p>', '𠮷', '𠮷'));
        self::assertTrue(LegacyJsonEncoding::introducesCorruption('<p>𐮷</p>', '𠮷'));
    }
}
