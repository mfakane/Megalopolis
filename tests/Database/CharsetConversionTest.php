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
    private static string $token = '';

    public function testUnfinishedMigrationShowsMaintenanceUntilTheAdministratorRetries(): void
    {
        $db = SearchOracle::mysql('mariadb-convert');
        // The first request migrates the r46 database and stamps it.
        Http::json(self::SERVICE, '/index.php?path=1.json');
        $version = $db->query("select value from meta where name = 'schemaVersion'")->fetchColumn();
        self::assertIsString($version);

        // Another request is migrating: visitors wait, then see maintenance.
        $db->exec("update meta set value = '0' where name = 'schemaVersion'");
        self::assertSame(1, (int)$db->query("select get_lock('megalopolis_schema_migration', 0)")->fetchColumn());
        try {
            $page = (new Browser(self::SERVICE))->request('GET', '');
            self::assertSame(503, $page['status']);
            self::assertContains('Retry-After: 60', $page['headers']);
            self::assertStringContainsString('更新処理中のため、現在メンテナンス中です', $page['body']);
            self::assertSame(503, (new Browser(self::SERVICE))->request('GET', '1.json')['status']);
            self::assertNotSame(503, (new Browser(self::SERVICE))->request('GET', 'login')['status']);
        } finally {
            $db->query("select release_lock('megalopolis_schema_migration')");
        }

        // A failed run is not retried automatically; the administrator reruns it.
        $db->exec("replace into meta (name, value) values ('schemaMigrationError', 'injected failure')");
        $page = (new Browser(self::SERVICE))->request('GET', '');
        self::assertSame(503, $page['status']);
        self::assertStringContainsString('管理者はログインして', $page['body']);
        self::assertStringNotContainsString('injected failure', $page['body']);

        $admin = new Browser(self::SERVICE);
        $form = $admin->request('GET', 'login');
        $login = $admin->request('POST', 'login', ['password' => self::ADMIN, 'token' => $this->formToken($form['body'])]);
        self::assertSame(302, $login['status'], $login['body']);
        $page = $admin->request('GET', '');
        self::assertSame(503, $page['status']);
        self::assertStringContainsString('injected failure', $page['body']);
        $forged = $admin->request('POST', 'util/migrate', ['token' => 'forged']);
        self::assertSame(403, $forged['status']);
        $rerun = $admin->request('POST', 'util/migrate', ['token' => $this->formToken($page['body'])]);
        self::assertSame(302, $rerun['status'], $rerun['body']);

        self::assertSame($version, $db->query("select value from meta where name = 'schemaVersion'")->fetchColumn());
        self::assertNull($db->query("select value from meta where name = 'schemaMigrationError'")->fetchColumn());
        self::assertSame(200, (new Browser(self::SERVICE))->request('GET', '')['status']);
    }

    public function testWorksWithSupplementaryCharactersAreStoredAsReferencesBeforeConversion(): array
    {
        $status = $this->convert('status');
        self::assertFalse($status['fullUnicode']);
        self::assertFalse($status['converting']);

        // The old r46 database needs both migrations; the admin page says so.
        self::assertSame(['charset', 'reindex'], $this->notices());

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
        // Jobs change state only by POST with the page's token, never by a link.
        $get = $this->browser()->request('GET', 'util/charset.json', ['p' => 'start']);
        self::assertSame(200, $get['status']);
        self::assertFalse($get['json']['converting']);
        $forged = $this->browser()->request('POST', 'util/charset.json', ['p' => 'start']);
        self::assertSame(403, $forged['status'], $forged['body']);
        $forged = $this->browser()->request('POST', 'util/reindex.json', ['p' => 'list', 'force' => 'yes', 'token' => 'forged']);
        self::assertSame(403, $forged['status'], $forged['body']);
        self::assertFalse($this->convert('status')['converting']);
        // Without the administrator's session the tools say so instead of an empty error.
        $expired = (new Browser(self::SERVICE))->request('POST', 'util/reindex.json', ['p' => 'list']);
        self::assertSame(401, $expired['status'], $expired['body']);
        self::assertStringContainsString('ログイン', $expired['json']['error']);

        $status = $this->convert('start');
        self::assertTrue($status['converting']);
        self::assertTrue($status['maintenance']);
        self::assertNotEmpty($this->tables('%' . '\_\_mb4'));
        self::assertSame(['charset'], $this->notices());

        $comment = $this->browser()->request('POST', '1/' . $before['id'] . '/comment.json', ['body' => 'mustnotpersist']);
        self::assertSame(503, $comment['status'], $comment['body']);

        $status = $this->convert('abort');
        self::assertFalse($status['converting']);
        self::assertFalse($status['maintenance']);
        self::assertSame([], $this->tables('%' . '\_\_mb4'));
        self::assertSame($before['counts'], $this->counts());

        return $before;
    }

    // A migrated database may lack index rows for old works; the conversion's
    // reindex below restores them.
    #[Depends('testStartStopsWritesUntilAborted')]
    public function testTotalCountComesFromTheWorksNotTheSearchIndex(array $before): array
    {
        $this->query('delete from searchIndex2 order by docid limit 3');
        $works = (int)$this->query('select count(*) n from threadEntry')[0]['n'];
        self::assertSame($works, $this->totalCount(''));

        // Old subject pages are revalidated when a work is posted elsewhere.
        $etag = $this->etag('1');
        $before['counted'] = $this->post('charsetcount');
        self::assertSame($works + 1, $this->totalCount('1'));
        self::assertNotSame($etag, $this->etag('1'));
        $before['counts'] = $this->counts();

        return $before;
    }

    #[Depends('testTotalCountComesFromTheWorksNotTheSearchIndex')]
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
        // Only the backup of the old tables is left to deal with.
        self::assertSame(['charset'], $this->notices());

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
        self::assertEqualsCanonicalizing([$before['id'], $before['counted'], $after], array_column($author->entries, 'id'));
        $tag = Http::json(self::SERVICE, '/index.php?path=' . rawurlencode('tag/分類' . self::EMOJI . '.json'));
        self::assertEqualsCanonicalizing([$before['id'], $before['counted'], $after], array_column($tag->entries, 'id'));

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
        self::assertSame([], $this->notices());
    }

    // PHP deletes idle sessions (24 minutes by default); the administrator
    // stays logged in, and a page opened before still submits its token.
    public function testAdminLoginOutlivesTheSession(): void
    {
        $admin = new Browser(self::SERVICE);
        $form = $admin->request('GET', 'login');
        $login = $admin->request('POST', 'login', ['password' => self::ADMIN, 'token' => $this->formToken($form['body'])]);
        self::assertSame(302, $login['status'], $login['body']);
        $token = $this->formToken($admin->request('GET', 'util/reindex')['body']);

        $session = '/^MEGALOPOLIS_(?!ADMIN_)/';
        $admin->forget($session);
        self::assertStringContainsString('ログアウト', $admin->request('GET', '')['body']);
        $admin->forget($session);
        $job = $admin->request('POST', 'util/reindex.json', ['p' => 'end', 'token' => $token]);
        self::assertSame(200, $job['status'], $job['body']);
        $forged = $admin->request('POST', 'util/reindex.json', ['p' => 'end', 'token' => str_repeat('0', 40)]);
        self::assertSame(403, $forged['status'], $forged['body']);

        self::assertSame(302, $admin->request('GET', 'logout')['status']);
        self::assertStringNotContainsString('ログアウト', $admin->request('GET', '')['body']);
        self::assertSame(401, $admin->request('POST', 'util/reindex.json', ['p' => 'end', 'token' => $token])['status']);
    }

    private function browser(): Browser
    {
        if (self::$browser === null) {
            self::$browser = new Browser(self::SERVICE);
            $token = self::$browser->token('util/charset');
            $login = self::$browser->request('POST', 'util/charset', ['password' => self::ADMIN, 'token' => $token]);
            self::assertSame(200, $login['status'], substr($login['body'], 0, 2000));
            // Logging in issues a new token, embedded in the tool's form.
            self::$token = self::$browser->token('util/charset');
        }
        return self::$browser;
    }

    private function formToken(string $html): string
    {
        $document = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
        $input = $document->querySelector('input[name="token"]');
        self::assertNotNull($input, 'No token in the form');
        return $input->getAttribute('value');
    }

    private function convert(string $action): array
    {
        $response = $action === 'status'
            ? $this->browser()->request('GET', 'util/charset.json')
            : $this->job('util/charset.json', ['p' => $action]);
        self::assertSame(200, $response['status'], $action . "\n" . substr($response['body'], 0, 2000));
        self::assertArrayNotHasKey('error', $response['json']);
        return $response['json'];
    }

    // The same requests as script/Util/Charset.js makes after finishing.
    private function reindex(): void
    {
        $data = $this->job('util/reindex.json', ['p' => 'list', 'force' => 'yes'])['json'];
        while ($data['next']) {
            $data = $this->job('util/reindex.json', ['p' => $data['next'], 'o' => $data['nextOffset'],
                'c' => $data['count'], 'b' => $data['buffer']])['json'];
        }
    }

    private function job(string $route, array $fields): array
    {
        $response = $this->browser()->request('POST', $route, $fields + ['token' => self::$token]);
        self::assertSame(200, $response['status'], $route . "\n" . substr($response['body'], 0, 2000));
        return $response;
    }

    /** The tools linked from the migration notices on the admin page. */
    private function notices(): array
    {
        $response = $this->browser()->request('GET', 'util');
        self::assertSame(200, $response['status']);
        $document = \Dom\HTMLDocument::createFromString($response['body'], LIBXML_NOERROR, 'UTF-8');
        return array_map(fn($a) => preg_replace('/^.*[\/=]util\//', '', $a->getAttribute('href')),
            iterator_to_array($document->querySelectorAll('ul.notify a')));
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

    /** 全作品数 on a subject page. */
    private function totalCount(string $route): int
    {
        $response = (new Browser(self::SERVICE))->request('GET', $route);
        self::assertSame(200, $response['status']);
        $document = \Dom\HTMLDocument::createFromString($response['body'], LIBXML_NOERROR, 'UTF-8');
        $term = $document->querySelector('dl.status dt');
        self::assertSame('全作品数', $term?->textContent);
        return (int)trim($term->nextElementSibling->textContent);
    }

    private function etag(string $route): string
    {
        $headers = (new Browser(self::SERVICE))->request('GET', $route)['headers'];
        $etag = preg_grep('/^ETag:/i', $headers);
        self::assertCount(1, $etag);
        return reset($etag);
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
