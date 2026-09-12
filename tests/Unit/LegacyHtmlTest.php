<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Configuration;
use Megalopolis\Thread;
use Megalopolis\ThreadEntry;
use Megalopolis\Visualizer;
use Megalopolis\Tests\Support\Fixtures;
use Megalopolis\Tests\Support\HtmlContract;
use Megalopolis\Tests\Support\LegacyHtml;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LegacyHtmlTest extends TestCase
{
    public static function examples(): iterable
    {
        $text = '春の図書館で、あいうえお・カキクケコ・漢字を読む。';
        yield 'all three breaks, no phantom break' => [21, 'void-breaks',
            '<p>' . $text . '</p><p>改行<br>その次<br>末尾<br></p><hr>',
            '<p>' . $text . '</p><p>改行<br></br>その次<br />末尾</p><hr></hr>'];
        yield 'attribute greater-than is content' => [261, 'attribute-greater-than',
            ' :REPLACED: <div data-value="a > b &amp; c">' . $text . '</div> :REPLACED: ',
            ' :REPLACED: <div data-value="a  b &amp; c">' . $text . '</div> :REPLACED: '];
        yield 'formatting reconstruction keeps the crossed italic text' => [281, 'formatting-reconstruction',
            '<p><b>' . $text . '<i>交差</i></b><i>終了</i></p><ul><li>未閉鎖</li><li>次</li></ul><div title="unquoted">末尾</div>',
            '<p><b>' . $text . '<i>交差</i></b>終了</i><ul><li>未閉鎖</li><li>次</li></ul><div title=unquoted>末尾</div></p>'];
        yield 'inert SVG spelling preserves viewBox' => [301, 'inert-custom-markup',
            '&lt;fixture-box data-case="legacy"&gt;' . $text . '&lt;/fixture-box&gt;<details><summary>開く</summary>内容</details>&lt;svg viewBox="0 0 10 10"&gt;&lt;text x="0" y="8"&gt;字&lt;/text&gt;&lt;/svg&gt;&lt;math&gt;&lt;mi&gt;x&lt;/mi&gt;&lt;mo&gt;+&lt;/mo&gt;&lt;mn&gt;1&lt;/mn&gt;&lt;/math&gt;',
            '&lt;fixture-box data-case="legacy"&gt;' . $text . '&lt;/fixture-box&gt;<details><summary>開く</summary>内容</details>&lt;svg viewbox="0 0 10 10"&gt;&lt;text x="0" y="8"&gt;字&lt;/text&gt;&lt;/svg&gt;&lt;math&gt;&lt;mi&gt;x&lt;/mi&gt;&lt;mo&gt;+&lt;/mo&gt;&lt;mn&gt;1&lt;/mn&gt;&lt;/math&gt;'];
        yield 'non-void split elements are not discarded' => [321, 'nonvoid-split',
            '<p>一頁 ' . $text . '</p><split><p>二頁</p><split><p>三頁</p></split></split>',
            '<p>一頁 ' . $text . '</p><p>二頁</p><split /><p>三頁</p>'];
        $punctuation = '「」『』【】〈〉《》〔〕（）…‥―—–〜～、。！？・￥¥＼\\';
        yield 'backslash does not eat the following span' => [224, 'backslash-attribute-truncation',
            '<div class="fixture" style="color:#123456"><span lang="ja" title="' . $punctuation . '">本文</span><font color="#654321" size="3">旧タグ</font><center>中央</center></div>',
            '<div class="fixture" style="color:#123456">'];
        yield 'backslash does not eat image dimensions' => [204, 'inert-image-backslash',
            '&lt;figure&gt;&lt;img src="fixture.png" alt="' . $punctuation . '" width="32" height="32"&gt;&lt;figcaption&gt;架空の画像&lt;/figcaption&gt;&lt;/figure&gt;',
            '&lt;figure&gt;&lt;img src="fixture.png" alt="' . $punctuation . '" width="&gt;&lt;figcaption&gt;架空の画像&lt;/figcaption&gt;&lt;/figure&gt;'];
        yield 'inert image attribute has one specified serialization' => [205, 'inert-image-quotes',
            '&lt;figure&gt;&lt;img src="fixture.png" alt="A&amp;amp;B &lt;tag&gt; &amp;quot;double&amp;quot; \'single\' \\path\\ %_?; SELECT 1 -- = + /" width="32" height="32"&gt;&lt;figcaption&gt;架空の画像&lt;/figcaption&gt;&lt;/figure&gt;',
            '&lt;figure&gt;&lt;img src="fixture.png" alt="A&amp;amp;B &amp;lt;tag&amp;gt; &amp;quot;double&amp;quot; &amp;#039;single&amp;#039; \\path\\ %_?; SELECT 1 -- = + /" width="32" height="32"&gt;&lt;figcaption&gt;架空の画像&lt;/figcaption&gt;&lt;/figure&gt;'];
        yield 'inert image NBSP is represented without loss' => [216, 'inert-image-nbsp',
            "&lt;figure&gt;&lt;img src=\"fixture.png\" alt=\"space [ ] full [　] tab [\t] NBSP [&amp;nbsp;] thin [ ]\" width=\"32\" height=\"32\"&gt;&lt;figcaption&gt;架空の画像&lt;/figcaption&gt;&lt;/figure&gt;",
            "&lt;figure&gt;&lt;img src=\"fixture.png\" alt=\"space [ ] full [　] tab [\t] NBSP [ ] thin [ ]\" width=\"32\" height=\"32\"&gt;&lt;figcaption&gt;架空の画像&lt;/figcaption&gt;&lt;/figure&gt;"];
        $plain = 'A&amp;B &lt;tag&gt; "double" \'single\' \\path\\ %_?; SELECT 1 -- = + /'
            . "\n二行目\r\n三行目\r四行目\t末尾  ";
        yield 'inert unknown element gets its closing tag without losing newlines' => [
            5, 'inert-unclosed-tag', $plain . '&lt;/tag&gt;', $plain];
    }

    #[DataProvider('examples')]
    public function testEachExceptionHasIndependentSourceAndOutputExpectations(
        int $ordinal, string $rule, string $current, string $old,
    ): void {
        $id = 1195084800 + $ordinal;
        $source = Fixtures::sqlitePayload($id);
        $prefix = sprintf('作品集%03d／作品%05d', intdiv($ordinal - 1, 100) + 1, $ordinal)
            . ($ordinal % 2 === 0 ? "<br />\r\n" : "\n");
        $contract = LegacyHtml::forWork('sqlite', $id, $source);
        self::assertNotNull($contract);
        self::assertSame('r46-html-' . $rule, $contract['rule']);
        self::assertSame($prefix . $current, $contract['current']);
        self::assertSame($prefix . $old, $contract['legacy']);
        self::assertTrue(LegacyHtml::accepts($contract, $prefix . $old, $prefix . $current));

        // Do not derive the renderer's expected output from the exception helper.
        foreach (['Board', 'ThreadEntry', 'Thread'] as $name) {
            require_once dirname(__DIR__, 2) . '/req/Model/' . $name . '.php';
        }
        Configuration::$instance = new Configuration();
        $entry = new ThreadEntry($id);
        $thread = new Thread($entry);
        $thread->body = $source['body'];
        $thread->convertLineBreak = $ordinal % 2 === 0;
        self::assertTrue(HtmlContract::equivalent($prefix . $current, Visualizer::escapeBody($thread)),
            'Renderer must satisfy the handwritten expectation, not merely match r46');
        self::assertSame($source['body'], $thread->body);
    }

    #[DataProvider('examples')]
    public function testAnExceptionNeverAcceptsUnreviewedChanges(
        int $ordinal, string $rule, string $current, string $old,
    ): void {
        $id = 1195084800 + $ordinal;
        $contract = LegacyHtml::forWork('sqlite', $id, Fixtures::sqlitePayload($id));
        self::assertNotNull($contract);
        self::assertFalse(LegacyHtml::accepts($contract, $contract['legacy'], $contract['legacy']),
            'Both versions reproducing the old bug must fail');
        self::assertFalse(LegacyHtml::accepts($contract, $contract['current'], $contract['legacy']));
        self::assertFalse(LegacyHtml::accepts($contract, '', $contract['current']));
        self::assertFalse(LegacyHtml::accepts($contract, $contract['legacy'], ''));
        self::assertFalse(LegacyHtml::accepts($contract, $contract['legacy'] . 'lost', $contract['current']));
        foreach ([
            $contract['current'] . 'extra',
            $contract['current'] . '<script>marker</script>',
            '<img src=x onerror=marker>' . $contract['current'],
            str_replace('作品集', '作品', $contract['current']),
            str_replace("\n", ' ', $contract['current']),
        ] as $wrong) {
            self::assertFalse(LegacyHtml::accepts($contract, $contract['legacy'], $wrong));
        }
    }

    public function testExceptionsAreBoundToAnUnchangedFrozenPayload(): void
    {
        $id = 1195084821;
        $source = Fixtures::sqlitePayload($id);
        self::assertNull(LegacyHtml::forWork('postgres', $id, $source));
        self::assertNull(LegacyHtml::forWork('sqlite', $id + 1, $source));
        self::assertNull(LegacyHtml::forWork('sqlite', 1, $source));
        self::assertNull(LegacyHtml::forWork('sqlite', 1195084801, Fixtures::sqlitePayload(1195084801)));
        foreach (['title', 'name', 'summary', 'body', 'afterword'] as $field) {
            $changed = $source;
            $changed[$field] = ($changed[$field] ?? '') . 'changed';
            self::assertNull(LegacyHtml::forWork('sqlite', $id, $changed), $field);
        }
        $changed = $source;
        $changed['tags'][2] .= 'changed';
        self::assertNull(LegacyHtml::forWork('sqlite', $id, $changed));
        self::assertNotNull(LegacyHtml::forWork('mysql', $id, $source));
    }

    public function testHistoricalUnicodeAndHtmlBugsCanCoexistWithoutCorruptingCurrentText(): void
    {
        $id = 1195085120; // Supplementary Unicode + custom markup.
        $source = Fixtures::sqlitePayload($id);
        $contract = LegacyHtml::forWork('sqlite', $id, $source);
        self::assertNotNull($contract);
        self::assertStringContainsString('𠮷野家 𩸽 𠮟る', $contract['current']);
        self::assertStringContainsString('𐮷野家 𙸽 𐮟る', $contract['legacy']);
        self::assertTrue(LegacyHtml::accepts($contract, $contract['legacy'], $contract['current']));
        self::assertFalse(LegacyHtml::accepts($contract, $contract['legacy'],
            str_replace('𠮷', '𐮷', $contract['current'])));
        self::assertNull(LegacyHtml::forWork('mysql', $id, $source),
            'SQLite supplementary text cannot authorize a MySQL exception');
    }

    public function testAll105ReviewedTextHtmlCombinationsPreserveTheirSpecifiedOutput(): void
    {
        foreach (['Board', 'ThreadEntry', 'Thread'] as $name) {
            require_once dirname(__DIR__, 2) . '/req/Model/' . $name . '.php';
        }
        Configuration::$instance = new Configuration();
        $coverage = [];
        for ($ordinal = 1; $ordinal <= 400; $ordinal++) {
            $id = 1195084800 + $ordinal;
            $source = Fixtures::sqlitePayload($id);
            $contract = LegacyHtml::forWork('sqlite', $id, $source);
            if ($contract === null) {
                continue;
            }
            $entry = new ThreadEntry($id);
            $thread = new Thread($entry);
            $thread->body = $source['body'];
            $thread->convertLineBreak = $ordinal % 2 === 0;
            self::assertTrue(HtmlContract::equivalent($contract['current'], Visualizer::escapeBody($thread)),
                $ordinal . ': ' . $contract['rule']);
            $coverage[$contract['rule']] = ($coverage[$contract['rule']] ?? 0) + 1;
        }
        self::assertEquals([
            'r46-html-void-breaks' => 20,
            'r46-html-attribute-greater-than' => 20,
            'r46-html-formatting-reconstruction' => 20,
            'r46-html-inert-custom-markup' => 20,
            'r46-html-nonvoid-split' => 20,
            'r46-html-backslash-attribute-truncation' => 1,
            'r46-html-inert-unclosed-tag' => 1,
            'r46-html-inert-image-backslash' => 1,
            'r46-html-inert-image-quotes' => 1,
            'r46-html-inert-image-nbsp' => 1,
        ], $coverage, 'A missing rule must not silently remove a regression case');
    }
}
