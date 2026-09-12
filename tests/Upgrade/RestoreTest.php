<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Upgrade;

use Megalopolis\Tests\Support\UpgradeFixtures as Fixture;
use Megalopolis\Tests\Support\UpgradeTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class RestoreTest extends UpgradeTestCase
{
    #[DataProvider('drivers')]
    public function testPreUpgradeBackupRestoresEveryOldRowKeyAndSearchIndexIntoAnEmptyStore(string $driver): void
    {
        $browser = $this->browser($driver, 'restored');
        $snapshot = $browser->snapshot();
        self::assertSame(Fixture::source()['works'], $snapshot['works']);
        self::assertSame([], $snapshot['orphans']);
        self::assertStringStartsWith('8.4.', $snapshot['php']);
        if ($driver === 'mysql') self::assertSame(getenv('UPGRADE_MYSQL_VERSION'), $snapshot['databaseVersion']);
        self::assertSame(Fixture::source()['works'], $browser->snapshot()['works']);
        $ids = array_column($this->response($browser, 'GET', '1.json')['json']['entries'], 'id');
        sort($ids, SORT_NUMERIC);
        self::assertSame(range(1200000001, 1200000006), $ids, 'Restore must not retain extra works');
        foreach (['preserve', 'edit', 'delete', 'comment', 'evaluate', 'admin'] as $i => $role) {
            self::assertSame([1200000001 + $i], $this->search($browser, ['query' => 'legacybody' . $role]));
        }
        self::assertSame([], $this->search($browser, ['query' => 'updatedbody']));
        self::assertSame([], $this->search($browser, ['title' => 'upgradecreated']));
        self::assertSame([], $this->search($browser, ['tag' => 'createdtag']));
        // Authenticated preview checks old keys without saving another mutation.
        foreach ([2 => '旧キー-2', 3 => '旧キー-3', 5 => 'legacy5'] as $i => $password) {
            $client = $this->browser($driver, 'restored');
            $this->response($client, 'POST', '1/' . (1200000000 + $i) . '/edit.json', ['password' => 'wrong'], 401);
            $response = $this->response($client, 'POST', '1/' . (1200000000 + $i) . '/edit', ['password' => $password]);
            $document = \Dom\HTMLDocument::createFromString($response['body'], LIBXML_NOERROR, 'UTF-8');
            $input = $document->querySelector('input[name="title"]');
            self::assertNotNull($input, 'Valid old key must open the edit form, not the login form');
            self::assertSame(Fixture::work($i)['entry']['title'], $input->getAttribute('value'));
            self::assertSame(Fixture::work($i), $this->work($driver, $i, 'restored'));
        }
    }
}
