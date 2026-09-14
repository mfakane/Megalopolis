<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Support;

/**
 * Reviewed r46 corpus defects, not a general HTML normalizer or renderer.
 * Literal source/output templates are independent of Visualizer and corpus.php.
 */
final class LegacyHtml
{
    public static function forWork(string $driver, int $id, array $payload): ?array
    {
        if (!in_array($driver, ['sqlite', 'mysql'], true)) {
            return null;
        }
        $row = Fixtures::manifest($driver)[$id] ?? null;
        if ($row === null || !isset($payload['tags']) || !is_array($payload['tags'])) {
            return null;
        }
        $values = [];
        foreach (['title', 'name', 'summary', 'body', 'afterword'] as $field) {
            if (!array_key_exists($field, $payload)
                || ($payload[$field] !== null && !is_string($payload[$field]))) {
                return null;
            }
            $values[] = $payload[$field];
        }
        foreach ($payload['tags'] as $tag) {
            if (!is_string($tag)) {
                return null;
            }
            $values[] = $tag;
        }
        // Neither a mutable current body nor a work ID alone authorizes a rule.
        if (Fixtures::digest($values) !== $row['hash']
            || !str_starts_with($payload['tags'][2] ?? '', '分類:')) {
            return null;
        }
        $raw = substr($payload['tags'][2], strlen('分類:'));
        $case = self::fragments($row['html'], $row['text'], $raw);
        if ($case === null) {
            return null;
        }
        $ordinal = $id - 1195084800;
        $prefix = sprintf("作品集%03d／作品%05d\n", $row['subject'], $ordinal);
        if ($payload['body'] !== $prefix . $case['source']) {
            return null;
        }
        $current = $prefix . $case['current'];
        $legacy = $prefix . $case['legacy'];
        if ($ordinal % 2 === 0) {
            $current = str_replace("\n", "<br />\r\n", str_replace(["\r\n", "\r"], "\n", $current));
            $legacy = str_replace("\n", "<br />\r\n", str_replace(["\r\n", "\r"], "\n", $legacy));
        }
        if ($driver === 'sqlite' && $row['text'] === 'supplementary') {
            $legacy = LegacyJsonEncoding::historical($legacy);
        }
        return ['rule' => 'r46-html-' . $case['rule'], 'current' => $current, 'legacy' => $legacy];
    }

    public static function accepts(array $contract, string $legacy, string $current): bool
    {
        return HtmlContract::equivalent($contract['current'], $current)
            && HtmlContract::equivalent($contract['legacy'], $legacy);
    }

    private static function fragments(string $htmlCase, string $textCase, string $raw): ?array
    {
        // The frozen generator escaped the parameter once, using HTML 4 quotes.
        $text = htmlspecialchars($raw, ENT_QUOTES | ENT_HTML401, 'UTF-8');
        switch ($htmlCase) {
            case 'paragraphs':
                return [
                    'rule' => 'void-breaks',
                    'source' => '<p>' . $text . '</p><p>改行<br>その次<br />末尾<br/></p><hr>',
                    'current' => '<p>' . $text . '</p><p>改行<br>その次<br>末尾<br></p><hr>',
                    'legacy' => '<p>' . $text . '</p><p>改行<br></br>その次<br />末尾</p><hr></hr>',
                ];
            case 'comments':
                return [
                    'rule' => 'attribute-greater-than',
                    'source' => '<!-- comment <b>not markup</b> --><div data-value="a > b &amp; c">' . $text . '</div><!-- 未完コメント',
                    'current' => ' :REPLACED: <div data-value="a > b &amp; c">' . $text . '</div> :REPLACED: ',
                    'legacy' => ' :REPLACED: <div data-value="a  b &amp; c">' . $text . '</div> :REPLACED: ',
                ];
            case 'malformed':
                return [
                    'rule' => 'formatting-reconstruction',
                    'source' => '<p><b>' . $text . '<i>交差</b>終了</i><ul><li>未閉鎖<li>次</ul><div title=unquoted>末尾',
                    'current' => '<p><b>' . $text . '<i>交差</i></b><i>終了</i></p><ul><li>未閉鎖</li><li>次</li></ul><div title="unquoted">末尾</div>',
                    'legacy' => '<p><b>' . $text . '<i>交差</i></b>終了</i><ul><li>未閉鎖</li><li>次</li></ul><div title=unquoted>末尾</div></p>',
                ];
            case 'pages':
                return [
                    'rule' => 'nonvoid-split',
                    'source' => '<p>一頁 ' . $text . '</p><split/><p>二頁</p><split /><p>三頁</p>',
                    'current' => '<p>一頁 ' . $text . '</p><split><p>二頁</p><split><p>三頁</p></split></split>',
                    'legacy' => '<p>一頁 ' . $text . '</p><p>二頁</p><split /><p>三頁</p>',
                ];
            case 'styles':
                if ($textCase !== 'punctuation') {
                    return null;
                }
                $source = '<div class="fixture" style="color:#123456"><span lang="ja" title="' . $text
                    . '">本文</span><font color="#654321" size="3">旧タグ</font><center>中央</center></div>';
                return ['rule' => 'backslash-attribute-truncation', 'source' => $source,
                    'current' => $source, 'legacy' => '<div class="fixture" style="color:#123456">'];
            case 'plain':
                if ($textCase !== 'quotes') {
                    return null;
                }
                $source = $raw . "\n二行目\r\n三行目\r四行目\t末尾  ";
                $legacy = self::literal($source);
                return ['rule' => 'inert-unclosed-tag', 'source' => $source,
                    'current' => $legacy . '&lt;/tag&gt;', 'legacy' => $legacy];
            case 'custom':
                $head = '<fixture-box data-case="legacy">';
                $middle = '<details><summary>開く</summary>内容</details>';
                $tail = '<svg viewBox="0 0 10 10"><text x="0" y="8">字</text></svg>'
                    . '<math><mi>x</mi><mo>+</mo><mn>1</mn></math>';
                return [
                    'rule' => 'inert-custom-markup',
                    'source' => $head . $text . '</fixture-box>' . $middle . $tail,
                    'current' => self::literal($head . self::text($raw) . '</fixture-box>')
                        . $middle . self::literal($tail),
                    'legacy' => self::literal($head . $text . '</fixture-box>')
                        . $middle . self::literal(str_replace('viewBox', 'viewbox', $tail)),
                ];
            case 'image':
                $rule = ['punctuation' => 'backslash', 'quotes' => 'quotes', 'spaces' => 'nbsp'][$textCase] ?? null;
                if ($rule === null) {
                    return null;
                }
                $head = '<figure><img src="fixture.png" alt="';
                $tail = '<figcaption>架空の画像</figcaption></figure>';
                $source = $head . $text . '" width="32" height="32">' . $tail;
                // These exact inert attribute spellings are reviewed display text,
                // not interchangeable HTML attributes after escaping the figure.
                $attribute = strtr($raw, ['&' => '&amp;', '"' => '&quot;', "\u{a0}" => '&nbsp;']);
                return [
                    'rule' => 'inert-image-' . $rule,
                    'source' => $source,
                    'current' => self::literal($head . $attribute . '" width="32" height="32">' . $tail),
                    'legacy' => self::literal($textCase === 'punctuation'
                        ? $head . $text . '" width=">' . $tail : $source),
                ];
        }
        return null;
    }

    /** Literal markup displayed as text, including existing entity spelling. */
    private static function literal(string $text): string
    {
        return strtr($text, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
    }

    /** Explicit text-node spelling inside the inert custom element. */
    private static function text(string $text): string
    {
        return strtr($text, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;', "\u{a0}" => '&nbsp;']);
    }
}
