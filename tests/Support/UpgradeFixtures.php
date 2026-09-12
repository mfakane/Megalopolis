<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Support;

final class UpgradeFixtures
{
    public const HASHES = [
        'data.sqlite' => '1694cc0e364653e5224fea09a8a0ad072fdafeca6280de8d14c70459f9fb598b',
        'search.sqlite' => 'ddbf1d7f892b2cf002da6e2b121ac206751c673255fc0b25459ae6c2f425ce59',
        'mysql-5.7.17.sql' => 'c4b1b82633f5e2ac46dec2a8f2785bbfa1e7dd34c5eb64822b38b6136411477e',
        'mysql-5.6.35.sql' => 'c4b1b82633f5e2ac46dec2a8f2785bbfa1e7dd34c5eb64822b38b6136411477e',
        'source.json' => 'd3677d8146d938f2431a88fc11b22842c448968ef7b010220acd105a7da92f82',
    ];

    public static function bytes(string $name): string
    {
        if (!isset(self::HASHES[$name])) throw new \InvalidArgumentException('Unknown frozen input');
        $path = dirname(__DIR__) . '/Fixtures/r46-upgrade/' . $name;
        $bytes = str_ends_with($name, '.json') ? file_get_contents($path)
            : gzdecode(file_get_contents($path . '.gz'));
        if ($bytes === false || hash('sha256', $bytes) !== self::HASHES[$name]) {
            throw new \RuntimeException('Frozen upgrade fixture changed: ' . $name);
        }
        return $bytes;
    }

    /** Expectations recorded in PHP 5.2.5 before r46 inserted the rows. */
    public static function source(): array
    {
        return json_decode(self::bytes('source.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function work(int $number): array
    {
        return self::source()['works'][$number - 1];
    }
}
