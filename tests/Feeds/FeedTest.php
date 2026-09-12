<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Feeds;

use DOMDocument;
use DOMXPath;
use Megalopolis\Tests\Support\Fixtures;
use Megalopolis\Tests\Support\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FeedTest extends TestCase
{
    public static function feeds(): iterable
    {
        foreach (['sqlite', 'mysql'] as $driver) {
            foreach (['rss', 'atom'] as $format) {
                foreach ([1, 100] as $subject) {
                    yield "$driver/$format/$subject" => [$driver, $format, $subject];
                }
            }
        }
    }

    #[DataProvider('feeds')]
    public function testRealRouteRendersACompleteFeed(string $driver, string $format, int $subject): void
    {
        if (getenv('COMPAT_HTTP') !== '1') {
            throw new \RuntimeException('Feed tests require: bash compat/test.sh');
        }
        $context = stream_context_create(['http' => [
            'timeout' => 60, 'ignore_errors' => true, 'follow_location' => 0,
            'header' => "Host: compat.test\r\nAccept-Encoding: identity\r\nUser-Agent: Megalopolis-feed-test\r\n",
        ]]);
        $body = file_get_contents("http://candidate-$driver:8080/index.php?path=$subject.$format", false, $context);
        $headers = $http_response_header ?? [];
        Http::artifact("feed-$driver-$format-$subject", ['headers' => $headers, 'body' => $body]);
        self::assertIsString($body);
        self::assertMatchesRegularExpression('~^HTTP/\S+ 200(?: |$)~', $headers[0] ?? '');
        self::assertMatchesRegularExpression(
            '~^Content-Type: application/' . $format . '\+xml(?:;[^\r\n]*)?$~im', implode("\n", $headers));

        $previous = libxml_use_internal_errors(true);
        try {
            libxml_clear_errors();
            $xml = new DOMDocument();
            $loaded = $xml->loadXML($body, LIBXML_NONET);
            $errors = array_map(fn($error) => trim($error->message), libxml_get_errors());
            self::assertTrue($loaded, implode('; ', $errors));
            self::assertSame([], $errors, 'No XML parser recovery or ignored malformed content');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('a', 'http://www.w3.org/2005/Atom');
        $root = $format === 'atom' ? '/a:feed' : '/rss/channel';
        $prefix = $format === 'atom' ? 'a:' : '';
        // Explicit release contract; update this expectation when changing App's version.
        self::assertSame('Megalopolis rc48', $xpath->evaluate("string($root/{$prefix}generator)"));
        if ($format === 'atom') {
            self::assertSame('48', $xpath->evaluate("string($root/a:generator/@version)"));
        } else {
            self::assertSame('2.0', $xpath->evaluate('string(/rss/@version)'));
        }
        $entries = $xpath->query($root . ($format === 'atom' ? '/a:entry' : '/item'));
        self::assertCount(100, $entries, 'A truncated or empty feed must not pass');
        $firstId = 1195084801 + ($subject - 1) * 100;
        foreach ($entries as $offset => $entry) {
            $id = $firstId + 99 - $offset;
            $link = $xpath->evaluate('string(' . $prefix . 'link' . ($format === 'atom' ? '/@href' : '') . ')', $entry);
            self::assertSame('compat.test', parse_url($link, PHP_URL_HOST));
            self::assertSame('/index.php', parse_url($link, PHP_URL_PATH));
            parse_str(parse_url($link, PHP_URL_QUERY) ?? '', $query);
            self::assertSame("$subject/$id", $query['path'] ?? null, 'Every ID and its order are checked');

            // The frozen SQLite oracle shares BMP cases with MySQL. The supplementary
            // case has intentionally different MySQL input, so it is checked here only on SQLite.
            if ($driver === 'mysql' && Fixtures::manifest($driver)[$id]['text'] === 'supplementary') continue;
            $expected = Fixtures::sqlitePayload($id);
            foreach (['title' => 'title', 'name' => $format === 'atom' ? 'author/a:name' : 'author'] as $field => $element) {
                self::assertSame(str_replace(["\r\n", "\r"], "\n", $expected[$field]),
                    $xpath->evaluate("string($prefix$element)", $entry), "$driver $id $field");
            }
            $categories = $xpath->query($prefix . 'category', $entry);
            $tags = [];
            foreach ($categories as $category) {
                $tags[] = $format === 'atom' ? $category->getAttribute('term') : $category->textContent;
            }
            // XML normalizes line endings in text and whitespace in attribute values.
            $expectedTags = array_map(fn($tag) => $format === 'atom'
                ? str_replace(["\r\n", "\r", "\n", "\t"], ' ', $tag)
                : str_replace(["\r\n", "\r"], "\n", $tag), $expected['tags']);
            self::assertSame($expectedTags, $tags, "$driver $id ordered categories");
        }
    }
}
