<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Configuration;
use Megalopolis\Thread;
use Megalopolis\ThreadEntry;
use Megalopolis\Visualizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VisualizerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Load model declarations only: no front controller, sessions or database.
        foreach (['Board', 'ThreadEntry', 'Thread'] as $name) {
            require_once dirname(__DIR__, 2) . '/req/Model/' . $name . '.php';
        }
    }

    protected function setUp(): void
    {
        Configuration::$instance = new Configuration();
    }

    private function thread(?string $body): Thread
    {
        $entry = new ThreadEntry(1);
        $entry->subject = 1;
        $thread = new Thread($entry);
        $thread->body = $body;
        $thread->convertLineBreak = false;
        return $thread;
    }

    public static function html(): iterable
    {
        yield 'allowed tags and attributes' => [
            '<P class="fixture" title="A &amp; B"><b>日本𠮷</b><br>次</P>',
            '<p class="fixture" title="A &amp; B"><b>日本𠮷</b><br>次</p>',
        ];
        yield 'events are not copied with the element' => [
            '<p id="x" onclick="marker"><img src="fixture.png" alt="絵" onerror="marker"></p>',
            '<p id="x"><img src="fixture.png" alt="絵"></p>',
        ];
        yield 'attribute text is preserved, not passed through strip_tags' => [
            '<div data-value="a > b &amp; c">text</div>', '<div data-value="a > b &amp; c">text</div>',
        ];
        yield 'comment markup never becomes active' => [
            '<!-- comment <b>not markup</b> --><p>text</p><!-- unfinished',
            ' :REPLACED: <p>text</p> :REPLACED: ',
        ];
        yield 'script subtree is removed' => ['前<script>marker()</script>後', '前 :REPLACED: 後'];
        yield 'form subtree is removed' => ['<form><input value="x"></form><p>後</p>', ' :REPLACED: <p>後</p>'];
        yield 'custom element remains inert text' => [
            '<fixture-box onload="marker">文字</fixture-box>',
            '&lt;fixture-box onload="marker"&gt;文字&lt;/fixture-box&gt;',
        ];
        yield 'safe URLs and CSS remain' => [
            '<a href="https://example.invalid/?a=1&amp;b=2" style="color:#123456">link</a>',
            '<a href="https://example.invalid/?a=1&amp;b=2" style="color:#123456">link</a>',
        ];
        yield 'javascript URL is removed' => ['<a href="javascript:void(0)">x</a>', '<a>x</a>'];
        yield 'mixed case and control characters in URL' => ["<a href=\" \tJaVa\nScRiPt:void(0)\">x</a>", '<a>x</a>'];
        yield 'entity-encoded scheme' => ['<a href="java&#x73;cript:void(0)">x</a>', '<a>x</a>'];
        yield 'data URL is removed' => ['<img src="data:text/html,marker" alt="safe">', '<img alt="safe">'];
        yield 'unsafe CSS removed' => ['<p style="background:url(javascript:marker)">x</p>', '<p>x</p>'];
        yield 'CSS escape and comments removed before validation' => [
            '<p style="width:ex/**/pr\\65 ssion(marker)">x</p>', '<p>x</p>',
        ];
        yield 'UTF-8 is not sniffed from user meta' => ['<meta charset="windows-1252"><p>日本𠮷</p>', ' :REPLACED: <p>日本𠮷</p>'];
        yield 'no active SVG namespace' => [
            '<svg onload="marker"><text>字</text></svg>',
            '&lt;svg onload="marker"&gt;&lt;text&gt;字&lt;/text&gt;&lt;/svg&gt;',
        ];
    }

    #[DataProvider('html')]
    public function testHtmlContract(string $input, string $expected): void
    {
        $thread = $this->thread($input);
        self::assertSame($expected, Visualizer::escapeBody($thread));
        $thread->afterword = $input;
        self::assertSame($expected, Visualizer::escapeAfterword($thread));
        self::assertSame($input, $thread->body, 'Formatting must not change stored data');
    }

    public function testConfiguredTagReplacementUsesANewElementAndFiltersItsAttributes(): void
    {
        Configuration::$instance->disallowedTags['blink'] = 'span';
        self::assertSame('<span title="ok">x</span>',
            Visualizer::escapeBody($this->thread('<blink title="ok" onclick="marker">x</blink>')));
    }

    public function testLiteralDisallowedAttributesAndTheStripFilterAreApplied(): void
    {
        Configuration::$instance->disallowedAttributes[] = 'title';
        self::assertSame('<b>x</b>', Visualizer::escapeBody(
            $this->thread('<p title="secret"><b>x</b></p>'), null, null, null, ['b']));
    }

    public function testABackslashAtTheEndOfAnAttributeDoesNotTruncateFollowingContent(): void
    {
        $html = '<div><span title="path\\">本文</span><b>後続</b></div>';
        self::assertSame($html, Visualizer::escapeBody($this->thread($html)));
    }

    public function testAllThreeBreaksIncludingTheTrailingOneArePreserved(): void
    {
        self::assertSame('<p>一<br>二<br>三<br></p>',
            Visualizer::escapeBody($this->thread('<p>一<br>二<br />三<br/></p>')));
    }

    public function testPageZeroAndNullKeepTheLegacyWholeBodyMeaning(): void
    {
        $thread = $this->thread('one<split/>two<split />three');
        self::assertSame(Visualizer::escapeBody($thread), Visualizer::escapeBody($thread, 0));
        self::assertSame('one', Visualizer::escapeBody($thread, 1));
        self::assertSame('two', Visualizer::escapeBody($thread, 2));
        self::assertSame('three', Visualizer::escapeBody($thread, 3));
    }

    public function testJsonKeepsTheLegacyArrayShapeIncludingItsHistoricalPageBoundary(): void
    {
        $config = Configuration::$instance;
        require dirname(__DIR__, 2) . '/compat/http/settings.php';
        $thread = $this->thread('one<split/>two<split />three');
        self::assertSame(['one<split>two<split>three</split></split>', 'one', 'two'],
            $thread->toArray()['formattedBody']);
        self::assertSame(['single'], $this->thread('single')->toArray()['formattedBody']);
    }

    public function testNullEmptyAndLineBreakConversion(): void
    {
        self::assertSame('', Visualizer::escapeBody($this->thread(null)));
        self::assertSame('', Visualizer::escapeBody($this->thread('')));
        $thread = $this->thread("a\nb\r\nc\rd");
        self::assertSame("a\nb\r\nc\rd", Visualizer::escapeBody($thread));
        $thread->convertLineBreak = true;
        self::assertSame("a<br />\r\nb<br />\r\nc<br />\r\nd", Visualizer::escapeBody($thread));
    }
}
