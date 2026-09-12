<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Tests\Support\HtmlContract;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlContractTest extends TestCase
{
    public function testOnlyEquivalentHtmlSpellingsAreAccepted(): void
    {
        self::assertTrue(HtmlContract::equivalent('<P id=x title="&quot;">&nbsp;&#39;<br /></P>',
            '<p title="&quot;" id="x">&#160;\'<br></p>'));
    }

    public static function differences(): iterable
    {
        yield 'escaped markup' => ['<p>x</p>', '&lt;p&gt;x&lt;/p&gt;'];
        yield 'lost text' => ['<p>abc</p>', '<p>ab</p>'];
        yield 'lost line break' => ['x<br>y', 'xy'];
        yield 'added event handler' => ['<img src="x">', '<img src="x" onerror="marker">'];
        yield 'added script' => ['<p>x</p>', '<p>x<script>marker</script></p>'];
        yield 'changed URL' => ['<a href="https://example.invalid">x</a>', '<a href="javascript:marker">x</a>'];
        yield 'element order' => ['<b>x</b><i>y</i>', '<i>y</i><b>x</b>'];
        yield 'attribute value' => ['<p title="a > b">x</p>', '<p title="a  b">x</p>'];
        yield 'text case' => ['&lt;svg viewbox&gt;', '&lt;svg viewBox&gt;'];
        yield 'Unicode' => ['𠮷', '𐮷'];
        yield 'whitespace' => ['a  b', 'a b'];
        yield 'line endings' => ["a\r\nb", "a\nb"];
        yield 'pre whitespace' => ["<pre>a\tb</pre>", '<pre>a b</pre>'];
    }

    #[DataProvider('differences')]
    public function testMeaningfulDifferencesRemainFailures(string $old, string $current): void
    {
        self::assertFalse(HtmlContract::equivalent($old, $current));
    }
}
