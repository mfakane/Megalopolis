<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Support;

use PHPUnit\Framework\TestCase;

abstract class UpgradeTestCase extends TestCase
{
    public static function drivers(): iterable
    {
        yield 'sqlite' => ['sqlite'];
        yield 'mysql' => ['mysql'];
    }

    protected function browser(string $driver, string $runtime = 'candidate'): UpgradeBrowser
    {
        return new UpgradeBrowser($runtime . '-' . $driver);
    }

    protected function work(string $driver, int $number, string $runtime = 'candidate'): ?array
    {
        return $this->browser($driver, $runtime)->snapshot(1200000000 + $number)['works'][0];
    }

    protected function response(UpgradeBrowser $browser, string $method, string $route,
        array $fields = [], int $status = 200): array
    {
        $response = $browser->request($method, $route, $fields);
        self::assertSame($status, $response['status'], $method . ' ' . $route . "\n" . substr($response['body'], 0, 2000));
        if (str_ends_with($route, '.json') && $status !== 302) {
            self::assertArrayHasKey('json', $response, 'Expected valid application/json');
            if ($status >= 400) self::assertArrayHasKey('error', $response['json']);
        }
        return $response;
    }

    protected function search(UpgradeBrowser $browser, array $query): array
    {
        $json = $this->response($browser, 'GET', 'search.json', $query)['json'];
        self::assertIsArray($json['entries']);
        return array_column($json['entries'], 'id');
    }

    protected function assertCounts(array $work, int $comments, int $evaluations, int $points, int $responses): void
    {
        self::assertCount($comments, $work['comments']);
        self::assertCount($evaluations, $work['evaluations']);
        self::assertSame($comments, $work['entry']['commentCount']);
        self::assertSame($evaluations, $work['entry']['evaluationCount']);
        self::assertSame($points, $work['entry']['points']);
        self::assertSame($responses, $work['entry']['responseCount']);
        self::assertSame($points, array_sum(array_column($work['evaluations'], 'point')));
    }

    protected function assertRecent(int $timestamp, int $begin): void
    {
        self::assertGreaterThanOrEqual($begin, $timestamp);
        self::assertLessThanOrEqual(time(), $timestamp);
    }
}
