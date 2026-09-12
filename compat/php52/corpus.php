<?php
// Synthetic, deterministic text. No real people or imported works.
function largeTextCases($unicode)
{
    return array(
        'japanese' => '春の図書館で、あいうえお・カキクケコ・漢字を読む。',
        'width' => 'ABC abc 0123 ＡＢＣ ａｂｃ ０１２３ ｶﾞｷﾞｸﾞｹﾞｺﾞ ガギグゲゴ',
        'variants' => '高橋・髙橋、崎・﨑、辺・邊・邉、斉・齊・齋、①②③ ⅠⅡⅢ ㈱㍻',
        'punctuation' => '「」『』【】〈〉《》〔〕（）…‥―—–〜～、。！？・￥¥＼\\',
        'quotes' => 'A&B <tag> "double" \'single\' \\path\\ %_?; SELECT 1 -- = + /',
        'combining' => "é e\xCC\x81 Å A\xCC\x8A が か\xE3\x82\x99 ハ\xE3\x82\x9A",
        'latin' => 'Café déjà vu Straße Ångström naïve façade smörgåsbord İstanbul',
        'greek' => 'Καλημέρα κόσμε · αβγδεζηθ · Ωμέγα',
        'cyrillic' => 'Привет, мир! Українська абетка: Ґ Є І Ї',
        'arabic' => 'مرحبا بالعالم العربية ١٢٣٤٥',
        'hebrew' => 'שלום עולם עברית 12345',
        'devanagari' => 'नमस्ते दुनिया हिन्दी संस्कृत',
        'hangul' => '안녕하세요 한글 가나다라 한글과 漢字',
        'chinese' => '简体中文：图书馆与测试。繁體中文：圖書館與測試。',
        'symbols' => '∀∃∈∉∑∏√∞≠≤≥±×÷ →←↑↓ ↔ ♠♥♦♣ ♪♫ ☀☂',
        'spaces' => "space [ ] full [　] tab [\t] NBSP [\xC2\xA0] thin [\xE2\x80\x89]",
        'invisible' => "ZWSP[\xE2\x80\x8B] ZWNJ[\xE2\x80\x8C] ZWJ[\xE2\x80\x8D] BOM[\xEF\xBB\xBF]",
        'direction' => "LTR[\xE2\x80\x8Eabc] RTL[\xE2\x80\x8Fשלום] embed[\xE2\x80\xABabc123\xE2\x80\xAC]",
        'entities-text' => '&amp; &lt; &gt; &quot; &#39; &#x65E5; &nbsp; &unknown; &amp;amp;',
        'supplementary' => $unicode ? '𠮷野家 𩸽 𠮟る 😀🧪 👩‍💻 🏳️‍🌈 𝄞 𐐷' : '吉野家 魚 叱る ☺☀ ♀♬ ☆★'
    );
}

function largeHtmlCases()
{
    return array(
        'plain' => "{raw}\n二行目\r\n三行目\r四行目\t末尾  ",
        'paragraphs' => '<p>{text}</p><p>改行<br>その次<br />末尾<br/></p><hr>',
        'inline' => '<p><b>太字</b><strong>強調</strong><i>斜体</i><em>{text}</em><u>下線</u><s>取消</s><small>小</small><sup>上</sup><sub>下</sub><mark>印</mark></p>',
        'ruby' => '<ruby>漢字<rp>（</rp><rt>かんじ</rt><rp>）</rp></ruby><ruby>図書館<rt>としょかん</rt></ruby><p>{text}</p>',
        'headings' => '<h1>第一章</h1><h2>第二節</h2><h3>{text}</h3><h4>四</h4><h5>五</h5><h6>六</h6>',
        'lists' => '<ul><li>{text}</li><li><ol><li>一</li><li>二</li></ol></li></ul><dl><dt>用語</dt><dd>説明</dd></dl>',
        'quotes' => '<blockquote cite="https://example.invalid/quote"><p>{text}</p></blockquote><q>短い引用</q><cite>架空の出典</cite>',
        'pre-code' => '<pre><code>&lt;?php echo &quot;A&amp;B&quot;; ?&gt;' . "\n\t" . '{text}</code></pre><kbd>Enter</kbd><samp>output</samp>',
        'tables' => '<table><caption>{text}</caption><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td rowspan="2">一</td><td>二</td></tr><tr><td>三</td></tr><tr><td colspan="2">結</td></tr></tbody></table>',
        'links' => '<a href="https://example.invalid/path?q=A&amp;b=%E6%97%A5#fragment" title="&quot;引用&quot;">{text}</a><a href="#local">内部</a><a id="local" href="mailto:fixture@example.invalid">mail</a>',
        'image' => '<figure><img src="fixture.png" alt="{text}" width="32" height="32"><figcaption>架空の画像</figcaption></figure>',
        'styles' => '<div class="fixture" style="color:#123456"><span lang="ja" title="{text}">本文</span><font color="#654321" size="3">旧タグ</font><center>中央</center></div>',
        'entities-html' => '<p>{text} &amp; &lt; &gt; &quot; &#39; &#169; &#x65E5; &nbsp; &unknown; &amp;lt;</p>',
        'comments' => '<!-- comment <b>not markup</b> --><div data-value="a > b &amp; c">{text}</div><!-- 未完コメント',
        'malformed' => '<p><b>{text}<i>交差</b>終了</i><ul><li>未閉鎖<li>次</ul><div title=unquoted>末尾',
        'custom' => '<fixture-box data-case="legacy">{text}</fixture-box><details><summary>開く</summary>内容</details><svg viewBox="0 0 10 10"><text x="0" y="8">字</text></svg><math><mi>x</mi><mo>+</mo><mn>1</mn></math>',
        'pages' => '<p>一頁 {text}</p><split/><p>二頁</p><split /><p>三頁</p>',
        'forms' => '<form><label>名前<input value="{text}" disabled></label><textarea readonly>&lt;textarea&gt;</textarea><select disabled><option>選択</option></select><button type="button" disabled>ボタン</button></form>',
        // Marker-only payloads exercise escaping/sanitization in consuming tests.
        'sanitization' => '<script>window.__r46Fixture = 1;</script><img src="fixture-missing.png" onerror="this.title=\'fixture-event\'"><a href="javascript:void(0)">{text}</a>',
        'long' => '<p>{text}</p>'
    );
}

function largeThread($ordinal, $unicode)
{
    $texts = largeTextCases($unicode);
    $html = largeHtmlCases();
    $textKeys = array_keys($texts);
    $htmlKeys = array_keys($html);
    $textKey = $textKeys[($ordinal - 1) % 20];
    $htmlKey = $htmlKeys[(int) (($ordinal - 1) / 20) % 20];
    $raw = $texts[$textKey];
    $escaped = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
    $thread = new Thread();
    $thread->id = 1195084800 + $ordinal;
    $thread->subject = (int) (($ordinal - 1) / 100) + 1;
    $entry = $thread->entry;
    $entry->title = sprintf('作品%05d [%s/%s] ', $ordinal, $textKey, $htmlKey) . $raw;
    // ASCII prefixes keep distinct authors distinct under MySQL's old collation.
    $entry->name = sprintf('作者%03d ', ($ordinal - 1) % 200 + 1) . mb_substr($raw, 0, 20);
    $entry->summary = $ordinal % 3 == 0 ? null : ($ordinal % 3 == 1 ? '' : "概要\n" . $raw);
    $entry->mail = $ordinal % 2 ? null : 'fixture' . $ordinal . '@example.invalid';
    $entry->link = $ordinal % 2 ? null : 'https://example.invalid/works/' . $ordinal;
    $entry->host = '192.0.2.' . (($ordinal - 1) % 254 + 1);
    $entry->tags = array('文字:' . $textKey, 'HTML:' . $htmlKey, '分類:' . $raw);
    $entry->dateTime = $thread->id;
    $entry->lastUpdate = $thread->id + 86400;
    $thread->body = sprintf("作品集%03d／作品%05d\n", $thread->subject, $ordinal)
        . strtr($html[$htmlKey], array('{text}' => $escaped, '{raw}' => $raw));
    if ($htmlKey == 'long')
        $thread->body .= str_repeat('<p>長文の段落。' . $escaped . '</p>' . "\n", 1024);
    $thread->afterword = $ordinal % 3 == 0 ? null : ($ordinal % 3 == 1 ? '' : '<p>あとがき ' . $escaped . '</p>');
    $thread->convertLineBreak = $ordinal % 2 == 0;
    $thread->writingMode = $ordinal % 3;
    $thread->foreground = $ordinal % 2 ? null : '#123456';
    $thread->background = $ordinal % 2 ? '#f5f5ff' : null;
    $thread->border = '#654321';
    $thread->hash = Util::hash('fixture');
    $entry->pageCount = $thread->pageCount();
    // Same byte-size calculation used by r46's ReadHandler::setValues().
    $entry->size = round(strlen(mb_convert_encoding($thread->body, 'Windows-31J', 'UTF-8')) / 1024, 2);
    return array($thread, $textKey, $htmlKey);
}
