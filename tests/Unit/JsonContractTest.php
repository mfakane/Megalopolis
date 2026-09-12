<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Tests\Support\JsonContract;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsonContractTest extends TestCase
{
    public function testOnlyObjectKeyOrderIsIgnored(): void
    {
        self::assertSame([], JsonContract::differences(json_decode('{"a":1,"b":["𠮷",null]}'),
            json_decode('{"b":["𠮷",null],"a":1}')));
    }

    public static function regressions(): iterable
    {
        yield 'integer to string' => ['{"id":1}', '{"id":"1"}', '$.id'];
        yield 'integer to float' => ['{"id":1}', '{"id":1.0}', '$.id'];
        yield 'null to empty' => ['{"body":null}', '{"body":""}', '$.body'];
        yield 'missing null field' => ['{"body":null}', '{}', '$.body'];
        yield 'extra field' => ['{}', '{"body":null}', '$.body'];
        yield 'object to array' => ['{}', '[]', '$'];
        yield 'list order' => ['[1,2]', '[2,1]', '$[0]'];
        yield 'extra list item' => ['[1]', '[1,2]', '$'];
        yield 'Unicode bytes' => ['{"body":"𠮷"}', '{"body":"吉"}', '$.body'];
        yield 'normalization' => ['{"body":"é"}', '{"body":"é"}', '$.body'];
        yield 'line ending' => ['{"body":"a\r\nb"}', '{"body":"a\nb"}', '$.body'];
        yield 'HTML' => ['{"formattedBody":["<b>x</b>"]}', '{"formattedBody":["x"]}', '$.formattedBody[0]'];
    }

    #[DataProvider('regressions')]
    public function testDetectsContractRegressions(string $expected, string $actual, string $path): void
    {
        self::assertArrayHasKey($path, JsonContract::differences(json_decode($expected), json_decode($actual)));
    }
}
