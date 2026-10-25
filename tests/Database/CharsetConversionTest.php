<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Database;

use Megalopolis\Tests\JsonApi\SearchCountTest;
use Megalopolis\Tests\Support\Browser;
use Megalopolis\Tests\Support\Drivers;
use Megalopolis\Tests\Support\Fixtures;
use Megalopolis\Tests\Support\Http;
use Megalopolis\Tests\Support\SearchOracle;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

/**
 * Converts the r46 MariaDB dump (utf8, strict sql_mode) to utf8mb4 through the
 * real administrator tool, on its own database. The methods run in order and
 * share that database: before, start/abort, conversion, after, cleanup.
 */
final class CharsetConversionTest extends TestCase
{
    private const string SERVICE = 'candidate-convert';
    private const string ADMIN = 'convert-admin';
    private const string EMOJI = '😀';
    private const string REFERENCE = '&#128512;';

    private static ?Browser $browser = null;

    public function testWorksWithSupplementaryCharactersAreStoredAsReferencesBeforeConversion(): array
    {
        $status = $this->convert('status');
        self::assertFalse($status['fullUnicode']);
        self::assertFalse($status['converting']);

        $id = $this->post('charsetbefore');
        $row = $this->entry($id);
        self::assertStringContainsString(self::REFERENCE, $row['title']);
        self::assertStringNotContainsString(self::EMOJI, $row['title']);

        $tag = Http::json(self::SERVICE, '/index.php?path=' . rawurlencode('1.json'))->entries[0]->tags[0];

        return [
            'id' => $id,
            'counts' => $this->counts(),
            'triggers' => $this->triggers(),
            'tag' => $tag,
            'tagged' => $this->search(['tag' => $tag]),
        ];
    }

    #[Depends('testWorksWithSupplementaryCharactersAreStoredAsReferencesBeforeConversion')]
    public function testStartStopsWritesUntilAborted(array $before): array
    {
        $status = $this->convert('start');
        self::assertTrue($status['converting']);
        self::assertTrue($status['maintenance']);
        self::assertNotEmpty($this->tables('%' . '\_\_mb4'));

        $comment = $this->browser()->request('POST', '1/' . $before['id'] . '/comment.json', ['body' => 'mustnotpersist']);
        self::assertSame(503, $comment['status'], $comment['body']);

        $status = $this->convert('abort');
        self::assertFalse($status['converting']);
        self::assertFalse($status['maintenance']);
        self::assertSame([], $this->tables('%' . '\_\_mb4'));
        self::assertSame($before['counts'], $this->counts());

        return $before;
    }

    #[Depends('testStartStopsWritesUntilAborted')]
    public function testConversionCopiesEveryRowAndRebuildsDerivedTables(array $before): array
    {
        $this->convert('start');
        for ($i = 0; ; $i++) {
            self::assertLessThan(1000, $i, 'Conversion did not finish');
            $status = $this->convert('step');
            if ($status['finished']) {
                break;
            }
        }
        $status = $this->convert('finish');
        self::assertTrue($status['fullUnicode']);
        self::assertFalse($status['converting']);
        self::assertFalse($status['maintenance']);
        $this->reindex();

        self::assertSame([], $this->tables('%' . '\_\_mb4'));
        self::assertNotEmpty($this->tables('%' . '\_\_utf8'));
        self::assertSame($before['counts'], $this->counts());
        self::assertSame($before['triggers'], $this->triggers());
        $collations = $this->query("select table_name, table_collation from information_schema.tables
            where table_schema = database() and table_type = 'BASE TABLE' and table_name not like '%\\_\\_utf8'");
        foreach ($collations as $row) {
            // The search index keeps a case-insensitive collation for FULLTEXT.
            self::assertStringStartsWith('utf8mb4_', $row['table_collation'], $row['table_name']);
        }
        self::assertSame(['utf8mb4_bin'], array_values(array_unique(array_column($this->query(
            "select table_collation from information_schema.tables where table_schema = database() and table_name = 'threadEntry'"),
            'table_collation'))));

        return $before;
    }

    #[Depends('testConversionCopiesEveryRowAndRebuildsDerivedTables')]
    public function testConvertedDataIsDecodedAndSearchable(array $before): array
    {
        $row = $this->entry($before['id']);
        self::assertStringContainsString(self::EMOJI, $row['title']);
        self::assertStringNotContainsString(self::REFERENCE, $row['title']);
        self::assertSame([$before['id']], $this->search(['title' => 'charsetbefore']));

        $after = $this->post('charsetafter');
        self::assertStringContainsString(self::EMOJI, $this->entry($after)['title']);
        self::assertSame([$after], $this->search(['title' => 'charsetafter']));

        // Author and tag lists are recomputed from the converted entries.
        $name = '作者' . self::EMOJI;
        $author = Http::json(self::SERVICE, '/index.php?path=' . rawurlencode('author/' . $name . '.json'));
        self::assertEqualsCanonicalizing([$before['id'], $after], array_column($author->entries, 'id'));
        $tag = Http::json(self::SERVICE, '/index.php?path=' . rawurlencode('tag/分類' . self::EMOJI . '.json'));
        self::assertEqualsCanonicalizing([$before['id'], $after], array_column($tag->entries, 'id'));

        // The rebuilt search index covers the existing works as before, and
        // being in the phrase format it finds exactly the works with the terms.
        self::assertSame($before['tagged'], $this->search(['tag' => $before['tag']]));
        $works = SearchOracle::works(SearchOracle::mysql('mariadb-convert'));
        foreach (SearchCountTest::QUERIES as $name => $query) {
            // MySQL folds case, width and accents; the oracle compares exactly.
            if (in_array($name, ['latin title', 'repeated grams'], true)) {
                continue;
            }
            self::assertSame(SearchOracle::expected($works, $query), SearchOracle::found($this->browser(), $query), $name);
        }

        return $before;
    }

    #[Depends('testConvertedDataIsDecodedAndSearchable')]
    public function testFixtureWorksAreUnchangedAndOldTablesCanBeDropped(): void
    {
        $manifest = Fixtures::manifest(Drivers::fixture('mariadb'));
        foreach ([1, 50, 100] as $subject) {
            foreach (Http::json(self::SERVICE, '/__compat/database?subject=' . $subject) as $row) {
                self::assertSame($manifest[$row->id]['hash'], Fixtures::digest($row->values), (string)$row->id);
            }
        }

        $status = $this->convert('dropOld');
        self::assertSame([], $status['oldTables']);
        self::assertSame([], $this->tables('%' . '\_\_utf8'));
    }

    private function browser(): Browser
    {
        if (self::$browser === null) {
            self::$browser = new Browser(self::SERVICE);
            $token = self::$browser->token('util/charset');
            $login = self::$browser->request('POST', 'util/charset', ['password' => self::ADMIN, 'token' => $token]);
            self::assertSame(200, $login['status'], substr($login['body'], 0, 2000));
        }
        return self::$browser;
    }

    private function convert(string $action): array
    {
        $response = $this->browser()->request('GET', 'util/charset.json', ['p' => $action]);
        self::assertSame(200, $response['status'], $action . "\n" . substr($response['body'], 0, 2000));
        self::assertArrayNotHasKey('error', $response['json']);
        return $response['json'];
    }

    // The same requests as script/Util/Charset.js makes after finishing.
    private function reindex(): void
    {
        $data = $this->json('util/reindex.json', ['p' => 'list', 'force' => 'yes']);
        while ($data['next']) {
            $data = $this->json('util/reindex.json', ['p' => $data['next'], 'o' => $data['nextOffset'],
                'c' => $data['count'], 'b' => $data['buffer']]);
        }
    }

    private function json(string $route, array $query): array
    {
        $response = $this->browser()->request('GET', $route, $query);
        self::assertSame(200, $response['status'], $route . "\n" . substr($response['body'], 0, 2000));
        return $response['json'];
    }

    private function post(string $marker): int
    {
        $browser = new Browser(self::SERVICE);
        $fields = ['title' => $marker . ' 絵文字' . self::EMOJI, 'name' => '作者' . self::EMOJI,
            'body' => $marker . 'body ' . self::EMOJI . '𠮷', 'tags' => '分類' . self::EMOJI . ' ' . $marker . 'tag',
            'editPassword' => 'convert-key', 'password' => ''];
        // Without adminOnly the real form carries no token.
        self::assertSame(200, $browser->request('GET', 'new')['status']);
        $response = $browser->request('POST', 'post.json', $fields);
        self::assertSame(302, $response['status'], substr($response['body'], 0, 2000));
        self::assertMatchesRegularExpression('/(\d{9,})/', $response['location']);
        preg_match('/(\d{9,})/', $response['location'], $match);
        return (int)$match[1];
    }

    private function search(array $query): array
    {
        return array_column($this->json('search.json', $query)['entries'], 'id');
    }

    private function entry(int $id): array
    {
        $rows = $this->query('select title, name, body from threadEntry join thread using (id) where id = ?', [$id]);
        self::assertCount(1, $rows);
        return $rows[0];
    }

    /** Row counts of the tables the conversion copies. */
    private function counts(): array
    {
        $counts = [];
        foreach (['thread', 'threadEntry', 'threadEvaluation', 'threadTag', 'threadStyle', 'threadPassword',
            'comment', 'evaluation', 'subject'] as $table) {
            $counts[$table] = (int)$this->query("select count(*) n from {$table}")[0]['n'];
        }
        return $counts;
    }

    private function triggers(): array
    {
        return array_column($this->query('select trigger_name from information_schema.triggers
            where trigger_schema = database() order by trigger_name'), 'trigger_name');
    }

    private function tables(string $like): array
    {
        return array_column($this->query('select table_name from information_schema.tables
            where table_schema = database() and table_name like ? order by table_name', [$like]), 'table_name');
    }

    private function query(string $sql, array $params = []): array
    {
        static $db = null;
        $db ??= new \PDO('mysql:host=mariadb-convert;dbname=megalopolis_r46;charset=utf8mb4', 'fixture', 'fixture',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $st = $db->prepare($sql);
        $st->execute($params);
        // information_schema returns upper-case column names on MariaDB.
        return array_map(fn($row) => array_change_key_case($row, CASE_LOWER), $st->fetchAll());
    }
}
