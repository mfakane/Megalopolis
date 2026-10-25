<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Support;

/**
 * Candidate database backends. MariaDB runs the same r46 MySQL dump (strict
 * sql_mode, as in production installs), so it shares the MySQL fixtures and
 * r46 baseline.
 */
final class Drivers
{
    public const array ALL = ['sqlite' => 'sqlite', 'mysql' => 'mysql', 'mariadb' => 'mysql'];

    /** Fixture manifest and r46 baseline service for a candidate backend. */
    public static function fixture(string $driver): string
    {
        return self::ALL[$driver];
    }

    /** @return iterable<string, array{string}> */
    public static function provider(): iterable
    {
        foreach (array_keys(self::ALL) as $driver) {
            yield $driver => [$driver];
        }
    }
}
