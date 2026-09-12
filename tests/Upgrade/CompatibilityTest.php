<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Upgrade;

use Megalopolis\Tests\Support\UpgradeFixtures as Fixture;
use Megalopolis\Tests\Support\UpgradeTestCase;
use Megalopolis\Util;
use PHPUnit\Framework\Attributes\DataProvider;

/** Each scenario owns a different old work, so execution order cannot hide a regression. */
final class CompatibilityTest extends UpgradeTestCase
{
    #[DataProvider('drivers')]
    public function testOldWorkResponsesPasswordsAndRepeatedOpenArePreserved(string $driver): void
    {
        $expected = Fixture::work(1);
        $payloads = [];
        foreach (['baseline', 'candidate'] as $runtime) {
            $browser = $this->browser($driver, $runtime);
            $snapshot = $browser->snapshot(1200000001);
            self::assertSame([$expected], $snapshot['works']);
            self::assertSame([], $snapshot['orphans']);
            self::assertSame($driver, $snapshot['driver']);
            if ($runtime === 'baseline') {
                self::assertSame('5.2.5', $snapshot['php']);
                self::assertSame($driver === 'sqlite' ? '3.15.2' : getenv('UPGRADE_MYSQL_VERSION'), $snapshot['databaseVersion']);
            } else {
                self::assertStringStartsWith('8.4.', $snapshot['php']);
                if ($driver === 'mysql') self::assertSame(getenv('UPGRADE_MYSQL_VERSION'), $snapshot['databaseVersion']);
            }
            self::assertSame([$expected], $browser->snapshot(1200000001)['works'], 'Opening twice is idempotent');
            $json = $this->response($browser, 'GET', '1/1200000001.json')['json'];
            self::assertSame($expected['body'], $json['body']);
            self::assertSame($expected['afterword'], $json['afterword']);
            self::assertSame($expected['entry']['tags'], $json['tags']);
            self::assertSame(20, $json['nonCommentEvaluation']);
            $comments = [];
            foreach ($expected['comments'] as $i => $row) {
                $comments[] = ['id' => $row['id'], 'name' => $row['name'], 'mail' => $row['mail'],
                    'body' => $row['body'], 'dateTime' => $row['dateTime'], 'evaluation' => $i === 0 ? 30 : null];
            }
            self::assertSame($comments, $json['comments']);
            $afterRead = $expected;
            $afterRead['entry']['readCount'] = 8;
            self::assertSame($afterRead, $this->work($driver, 1, $runtime));
            $this->response($browser, 'GET', '1/1200000001.json');
            self::assertSame($afterRead, $this->work($driver, 1, $runtime), 'Same visitor must not increment twice');
            $payloads[$runtime] = $json;
        }
        // This simple fixture contains no accepted legacy HTML bugs.
        self::assertSame($payloads['baseline'], $payloads['candidate']);
    }

    #[DataProvider('drivers')]
    public function testNativeOldKeyCanEditRotateKeyAndUpdateSearch(string $driver): void
    {
        $browser = $this->browser($driver);
        $before = Fixture::work(2);
        self::assertSame($before, $this->work($driver, 2));
        self::assertSame([1200000002], $this->search($browser, ['query' => 'legacybodyedit']));
        $fields = ['title' => 'updatedwork 編集後', 'body' => "<p>updatedbody 日本語</p>\r\n次行",
            'tags' => '更新分類 updatedtag', 'editPassword' => 'new-edit-key',
            // The old/current form validator accepts only http: author links.
            'link' => 'http://example.invalid/updated'];
        $begin = time();
        $this->response($browser, 'POST', '1/1200000002/post.json', ['password' => '旧キー-2'] + $fields, 302);
        $after = $this->work($driver, 2);
        $this->assertRecent($after['entry']['lastUpdate'], $begin);
        self::assertNotEmpty($after['entry']['host']);
        self::assertNotSame($before['entry']['host'], $after['entry']['host']);
        self::assertSame(Util::HASH_TYPE_MEGALOPOLIS1, Util::hashEquals($after['hash'], 'new-edit-key'));
        self::assertFalse(Util::hashEquals($after['hash'], '旧キー-2'));
        $expected = $before;
        $expected['entry']['title'] = $fields['title'];
        $expected['entry']['link'] = $fields['link'];
        $expected['entry']['tags'] = ['更新分類', 'updatedtag'];
        $expected['entry']['lastUpdate'] = $after['entry']['lastUpdate'];
        $expected['entry']['host'] = $after['entry']['host'];
        $expected['hash'] = $after['hash'];
        $expected['body'] = $fields['body'];
        self::assertSame($expected, $after, 'Only the explicitly edited fields may change');
        self::assertSame([], $this->search($browser, ['query' => 'legacybodyedit']));
        self::assertSame([1200000002], $this->search($browser, ['query' => 'updatedbody']));
        self::assertSame([1200000002], $this->search($browser, ['tag' => 'updatedtag']));
        $rejected = $browser->request('POST', '1/1200000002/post.json', ['password' => '旧キー-2', 'title' => 'mustnotpersist']);
        self::assertSame($after, $this->work($driver, 2));
        $this->response($browser, 'POST', '1/1200000002/post.json', ['password' => 'new-edit-key', 'title' => $fields['title']], 302);
        self::assertSame($fields['title'], $this->work($driver, 2)['entry']['title']);
        self::assertSame($before, $this->work($driver, 2, 'baseline'));
        self::assertSame(401, $rejected['status'], 'Rotated old key must be rejected with 401, not 500');
    }

    #[DataProvider('drivers')]
    public function testSha1OldKeyCanDeleteWorkResponsesAndSearchIndex(string $driver): void
    {
        $browser = $this->browser($driver);
        $before = Fixture::work(3);
        self::assertSame($before, $this->work($driver, 3));
        self::assertSame([1200000003], $this->search($browser, ['query' => 'legacybodydelete']));
        $this->response($browser, 'POST', '1/1200000003/unpost.json', ['password' => 'wrong'], 401);
        self::assertSame($before, $this->work($driver, 3));
        $this->response($browser, 'POST', '1/1200000003/unpost.json', ['password' => '旧キー-3'], 302);
        $snapshot = $browser->snapshot(1200000003);
        self::assertSame([null], $snapshot['works']);
        self::assertSame([['id' => 1200000003, 'comments' => 0, 'evaluations' => 0]], $snapshot['orphans']);
        $missing = $browser->request('GET', '1/1200000003.json');
        self::assertSame([], $this->search($browser, ['query' => 'legacybodydelete']));
        self::assertSame([], $this->search($browser, ['tag' => 'role-delete']));
        self::assertSame($before, $this->work($driver, 3, 'baseline'));
        self::assertSame(404, $missing['status'], 'Deleted work must return 404, not 500');
    }

    #[DataProvider('drivers')]
    public function testOldCommentKeyAndNewCommentMaintainLinkedEvaluationAndCounters(string $driver): void
    {
        $browser = $this->browser($driver);
        $before = Fixture::work(4);
        self::assertSame($before, $this->work($driver, 4));
        $this->response($browser, 'POST', '1/1200000004/uncomment.json', ['id' => 1200001041, 'password' => 'wrong'], 401);
        self::assertSame($before, $this->work($driver, 4));
        $this->response($browser, 'POST', '1/1200000004/uncomment.json', ['id' => 1200001041, 'password' => '旧コメント-4-1']);
        $deleted = $this->work($driver, 4);
        $this->assertCounts($deleted, 1, 1, 20, 2);
        self::assertSame([$before['comments'][1]], $deleted['comments']);
        self::assertSame([$before['evaluations'][1]], $deleted['evaluations']);
        $this->response($browser, 'POST', '1/1200000004/comment.json',
            ['name' => '新読者', 'body' => '', 'password' => 'new-comment-key', 'point' => 30], 400);
        self::assertSame($deleted, $this->work($driver, 4));
        $begin = time();
        $fields = ['name' => '新読者', 'mail' => 'reader@example.invalid', 'body' => "更新後の感想\r\n<b>HTML</b> &amp; 引用",
            'password' => 'new-comment-key', 'point' => 30];
        $json = $this->response($browser, 'POST', '1/1200000004/comment.json', $fields)['json'];
        self::assertSame($fields['body'], $json['body']);
        self::assertSame(30, $json['evaluation']);
        $this->assertRecent($json['id'], $begin);
        $after = $this->work($driver, 4);
        $this->assertCounts($after, 2, 2, 50, 3);
        self::assertSame($before['comments'][1], $after['comments'][0]);
        self::assertSame($before['evaluations'][1], $after['evaluations'][0]);
        $comment = $after['comments'][1];
        self::assertSame($json['id'], $comment['id']);
        self::assertSame($json['id'], $comment['evaluation']);
        self::assertSame(1200000004, $comment['entryID']);
        self::assertSame($fields['body'], $comment['body']);
        self::assertSame($fields['name'], $comment['name']);
        self::assertSame($fields['mail'], $comment['mail']);
        self::assertSame(Util::HASH_TYPE_MEGALOPOLIS1, Util::hashEquals($comment['hash'], 'new-comment-key'));
        self::assertSame(['id' => $comment['id'], 'entryID' => 1200000004, 'point' => 30,
            'host' => $comment['host'], 'dateTime' => $comment['dateTime']], $after['evaluations'][1]);
        $this->response($browser, 'POST', '1/1200000004/uncomment.json', ['id' => $comment['id'], 'password' => 'new-comment-key']);
        $final = $this->work($driver, 4);
        $this->assertCounts($final, 1, 1, 20, 2);
        self::assertSame($deleted['comments'], $final['comments']);
        self::assertSame($deleted['evaluations'], $final['evaluations']);
        self::assertSame($before, $this->work($driver, 4, 'baseline'));
    }

    #[DataProvider('drivers')]
    public function testDesOldKeyAndEvaluationAddDuplicateRejectAndRemove(string $driver): void
    {
        $browser = $this->browser($driver);
        $before = Fixture::work(5);
        self::assertSame($before, $this->work($driver, 5));
        $this->response($browser, 'POST', '1/1200000005/post.json', ['password' => 'legacy5',
            'title' => $before['entry']['title'], 'link' => 'http://example.invalid/old/5'], 302);
        $authenticated = $this->work($driver, 5);
        self::assertSame($before['hash'], $authenticated['hash']);
        self::assertSame($before['comments'], $authenticated['comments']);
        self::assertSame($before['evaluations'], $authenticated['evaluations']);
        $this->response($browser, 'POST', '1/1200000005/unevaluate.json', ['id' => 1200002005], 403);
        self::assertSame($authenticated, $this->work($driver, 5));
        $begin = time();
        $json = $this->response($browser, 'POST', '1/1200000005/evaluate.json', ['point' => 20])['json'];
        self::assertSame(20, $json['point']);
        $this->assertRecent($json['id'], $begin);
        $after = $this->work($driver, 5);
        $this->assertCounts($after, 2, 3, 70, 4);
        self::assertSame($before['comments'], $after['comments']);
        self::assertSame($before['evaluations'], array_slice($after['evaluations'], 0, 2));
        self::assertSame($json['id'], $after['evaluations'][2]['id']);
        $this->response($browser, 'POST', '1/1200000005/evaluate.json', ['point' => 20], 400);
        self::assertSame($after, $this->work($driver, 5));
        $this->response($browser, 'POST', '1/1200000005/unevaluate.json', ['id' => $json['id']]);
        $final = $this->work($driver, 5);
        $this->assertCounts($final, 2, 2, 50, 3);
        self::assertSame($before['comments'], $final['comments']);
        self::assertSame($before['evaluations'], $final['evaluations']);
        self::assertSame($before, $this->work($driver, 5, 'baseline'));
    }

    #[DataProvider('drivers')]
    public function testOldAdminHashAndRealCsrfAllowNewPost(string $driver): void
    {
        $browser = $this->browser($driver);
        self::assertSame(Fixture::work(6), $this->work($driver, 6));
        $fields = ['title' => 'upgradecreated 新作品', 'name' => '新作者', 'body' => "<p>upgradecreatedbody 日本語</p>\r\n二行目",
            'tags' => '新分類 createdtag', 'editPassword' => 'created-key', 'convertLineBreak' => 'false'];
        $token = $browser->token();
        $begin = time();
        $response = $this->response($browser, 'POST', 'post.json', ['password' => 'upgrade-admin', 'token' => $token] + $fields, 302);
        $ids = $this->search($browser, ['title' => 'upgradecreated']);
        self::assertCount(1, $ids);
        $id = $ids[0];
        $this->assertRecent($id, $begin);
        self::assertStringContainsString((string)$id, $response['location']);
        $work = $this->browser($driver)->snapshot($id)['works'][0];
        self::assertSame($fields['title'], $work['entry']['title']);
        self::assertSame($fields['name'], $work['entry']['name']);
        self::assertSame(['新分類', 'createdtag'], $work['entry']['tags']);
        self::assertSame($fields['body'], $work['body']);
        self::assertSame(false, $work['convertLineBreak']);
        self::assertSame(Util::HASH_TYPE_MEGALOPOLIS1, Util::hashEquals($work['hash'], 'created-key'));
        $this->assertCounts($work, 0, 0, 0, 0);
        self::assertSame([$id], $this->search($browser, ['query' => 'upgradecreatedbody']));
        self::assertSame([$id], $this->search($browser, ['tag' => 'createdtag']));
        self::assertSame(Fixture::work(6), $this->work($driver, 6));
        self::assertSame([], $this->search($this->browser($driver, 'baseline'), ['title' => 'upgradecreated']));
    }

    #[DataProvider('drivers')]
    public function testWrongWorkKeyCannotChangeOldWorkAndReturnsUnauthorized(string $driver): void
    {
        $browser = $this->browser($driver);
        $before = Fixture::work(6);
        self::assertSame($before, $this->work($driver, 6));
        $response = $browser->request('POST', '1/1200000006/post.json', ['password' => 'wrong', 'title' => 'mustnotpersist']);
        self::assertSame($before, $this->work($driver, 6), 'Rejected write must not modify even one old field');
        self::assertArrayHasKey('error', $response['json']);
        self::assertSame(401, $response['status']);
    }

    public static function rejectedPosts(): iterable
    {
        foreach (['sqlite', 'mysql'] as $driver) {
            yield $driver . '-csrf' => [$driver, false, 'upgrade-admin', 403, 'rejectedcsrf'];
            yield $driver . '-password' => [$driver, true, 'wrong', 401, 'rejectedpassword'];
        }
    }

    #[DataProvider('rejectedPosts')]
    public function testNewPostRequiresCsrfAndOldAdminPassword(string $driver, bool $sendToken,
        string $password, int $status, string $title): void
    {
        $browser = $this->browser($driver);
        $token = $browser->token();
        $before = $this->response($browser, 'GET', '1.json')['json']['entries'];
        $fields = ['title' => $title, 'body' => 'rejectedpostbody', 'name' => '新作者',
            'editPassword' => 'created-key', 'password' => $password];
        if ($sendToken) $fields['token'] = $token;
        $response = $browser->request('POST', 'post.json', $fields);
        self::assertSame($before, $this->response($browser, 'GET', '1.json')['json']['entries'],
            'Rejected posting must not add an unindexed row or change the collection');
        self::assertSame([], $this->search($browser, ['title' => $title]));
        self::assertSame(Fixture::work(6), $this->work($driver, 6));
        self::assertArrayHasKey('error', $response['json']);
        self::assertSame($status, $response['status']);
    }
}
