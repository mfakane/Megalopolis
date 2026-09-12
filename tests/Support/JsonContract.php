<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Support;

final class JsonContract
{
    /** Ignore object key order only. JSON array order, types and string bytes matter. */
    public static function differences(mixed $expected, mixed $actual, string $path = '$'): array
    {
        if (get_debug_type($expected) !== get_debug_type($actual)) {
            return [$path => ['expected' => $expected, 'actual' => $actual]];
        }
        if ($expected instanceof \stdClass) {
            $left = get_object_vars($expected);
            $right = get_object_vars($actual);
            $keys = array_unique(array_merge(array_keys($left), array_keys($right)));
            $diff = [];
            foreach ($keys as $key) {
                $child = $path . '.' . $key;
                if (!array_key_exists($key, $left) || !array_key_exists($key, $right)) {
                    $diff[$child] = ['missing' => !array_key_exists($key, $left) ? 'expected' : 'actual'];
                } else {
                    $diff += self::differences($left[$key], $right[$key], $child);
                }
            }
            return $diff;
        }
        if (is_array($expected)) {
            if (count($expected) !== count($actual)) {
                return [$path => ['expectedCount' => count($expected), 'actualCount' => count($actual)]];
            }
            $diff = [];
            foreach ($expected as $index => $value) {
                $diff += self::differences($value, $actual[$index], $path . '[' . $index . ']');
            }
            return $diff;
        }
        return $expected === $actual ? [] : [$path => ['expected' => $expected, 'actual' => $actual]];
    }
}
