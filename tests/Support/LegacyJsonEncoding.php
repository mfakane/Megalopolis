<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Support;

final class LegacyJsonEncoding
{
    // Exact characters in the frozen corpus. Verified directly with PHP 5.2.5
    // json_encode; this is NOT a reversible normalization of arbitrary Unicode.
    private const CORRUPTION = ['𠮷' => '𐮷', '𩸽' => '𙸽', '𠮟' => '𐮟'];

    public static function historical(string $correct): string
    {
        return strtr($correct, self::CORRUPTION);
    }

    public static function acceptsRaw(mixed $old, mixed $current, mixed $frozen): bool
    {
        return is_string($frozen) && $current === $frozen && $old !== $current
            && $old === self::historical($frozen);
    }

    public static function introducesCorruption(string $current, string $source): bool
    {
        foreach (self::CORRUPTION as $correct => $corrupt) {
            if (str_contains($current, $corrupt) && !str_contains($source, $corrupt)) {
                return true;
            }
        }
        return false;
    }

    public static function acceptsHtml(string $old, string $current, string $source): bool
    {
        return self::historical($source) !== $source && self::historical($current) !== $current
            && !self::introducesCorruption($current, $source)
            && HtmlContract::equivalent($old, self::historical($current));
    }

    /** Correct only known corruptions proven by the corresponding immutable input. */
    public static function rawFields(string $driver, mixed $old, mixed $current): array
    {
        if ($driver !== 'sqlite' || !$old instanceof \stdClass || !$current instanceof \stdClass) {
            return [];
        }
        $fields = [];
        $entries = isset($current->entries) ? $current->entries : [$current->entry ?? null];
        foreach ($entries as $offset => $entry) {
            $legacy = isset($current->entries) ? ($old->entries[$offset] ?? null) : ($old->entry ?? null);
            if (!$entry instanceof \stdClass || !$legacy instanceof \stdClass || !is_int($entry->id ?? null)
                || $legacy->id !== $entry->id || !isset(Fixtures::manifest('sqlite')[$entry->id])
                || Fixtures::manifest('sqlite')[$entry->id]['text'] !== 'supplementary') {
                continue;
            }
            $payload = Fixtures::sqlitePayload($entry->id);
            $prefix = isset($current->entries) ? '$.entries[' . $offset . ']' : '$.entry';
            foreach (['title', 'name', 'summary'] as $name) {
                if (property_exists($entry, $name) && property_exists($legacy, $name)) {
                    $fields[$prefix . '.' . $name] = ['expected' => $legacy->$name,
                        'actual' => $entry->$name, 'source' => $payload[$name]];
                }
            }
            foreach ($payload['tags'] as $index => $tag) {
                if (isset($entry->tags[$index], $legacy->tags[$index])) {
                    $fields[$prefix . '.tags[' . $index . ']'] = ['expected' => $legacy->tags[$index],
                        'actual' => $entry->tags[$index], 'source' => $tag];
                }
            }
            if (!isset($current->entries)) {
                foreach (['body', 'afterword'] as $name) {
                    if (property_exists($old, $name) && property_exists($current, $name)) {
                        $fields['$.' . $name] = ['expected' => $old->$name,
                            'actual' => $current->$name, 'source' => $payload[$name]];
                    }
                }
                foreach ($payload['tags'] as $index => $tag) {
                    if (isset($current->tags[$index], $old->tags[$index])) {
                        $fields['$.tags[' . $index . ']'] = ['expected' => $old->tags[$index],
                            'actual' => $current->tags[$index], 'source' => $tag];
                    }
                }
            }
        }
        return $fields;
    }
}
