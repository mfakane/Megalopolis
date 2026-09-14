<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Support;

final class Fixtures
{
    public const HASHES = [
        'data.sqlite' => 'c66520dfc94a772afe989253d1dfce5e523acb92ccffd1c686a1d27351156179',
        'search.sqlite' => '16fbaf6b2e9be36d7eea6195f9115d40aff94f21491817242cc967e2f4defcea',
        'mysql.sql' => 'c8cf87e0d627ecb5dd857e2aa6b0963e62c8d2087c78a0e9c917a578a8351415',
        'large-sqlite.tsv' => '4ff32d15ac231fbe72c33d0724642dba77adb28c4f3a4c76356fbb698ebe89c0',
        'large-mysql.tsv' => 'c0cc2bae30bd97a5add41e6876b820b38e78ebc76a371cf011ef7eed38efbc01',
    ];

    public static function path(string $name): string
    {
        return dirname(__DIR__) . '/Fixtures/r46/' . $name . '.gz';
    }

    public static function manifest(string $driver): array
    {
        static $manifests = [];
        if (!isset($manifests[$driver])) {
            $lines = explode("\n", trim(gzdecode(file_get_contents(self::path('large-' . $driver . '.tsv')))));
            if (array_shift($lines) !== "id\tsubject\ttext_case\thtml_case\tbody_bytes\tpages\tpayload_sha256") {
                throw new \RuntimeException('Invalid r46 manifest header');
            }
            $rows = [];
            foreach ($lines as $line) {
                [$id, $subject, $text, $html, $bytes, $pages, $hash] = explode("\t", $line);
                $rows[(int) $id] = ['subject' => (int) $subject, 'text' => $text, 'html' => $html,
                    'bytes' => (int) $bytes, 'pages' => (int) $pages, 'hash' => $hash];
            }
            $manifests[$driver] = $rows;
        }
        return $manifests[$driver];
    }

    public static function digest(array $values): string
    {
        $hash = hash_init('sha256');
        foreach ($values as $value) {
            if ($value !== null && !is_string($value)) {
                throw new \InvalidArgumentException('Payload fields must be strings or null');
            }
            hash_update($hash, $value === null ? 'N;' : 'S' . strlen($value) . ':' . $value . ';');
        }
        return hash_final($hash);
    }

    /** Independent, immutable r46 oracle; never reads the candidate database. */
    public static function sqlitePayload(int $id): array
    {
        static $database = null;
        static $payloads = [];
        if ($database === null) {
            $path = tempnam(sys_get_temp_dir(), 'r46-oracle-');
            $input = gzopen(self::path('data.sqlite'), 'rb');
            $output = fopen($path, 'wb');
            stream_copy_to_stream($input, $output);
            gzclose($input);
            fclose($output);
            register_shutdown_function(static function () use ($path): void { unlink($path); });
            if (hash_file('sha256', $path) !== self::HASHES['data.sqlite']) {
                throw new \RuntimeException('Frozen r46 SQLite oracle has changed');
            }
            $database = new \PDO('sqlite:' . $path, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $database->exec('PRAGMA query_only = ON');
        }
        if (!isset($payloads[$id])) {
            $query = $database->prepare('SELECT e.title, e.name, e.summary, t.body, t.afterword
                FROM threadEntry e JOIN thread t ON t.id = e.id WHERE e.id = ?');
            $query->execute([$id]);
            $row = $query->fetch(\PDO::FETCH_ASSOC);
            if ($row === false) {
                throw new \RuntimeException('No frozen r46 work: ' . $id);
            }
            $query = $database->prepare('SELECT tag FROM threadTag WHERE id = ? ORDER BY position');
            $query->execute([$id]);
            $row['tags'] = $query->fetchAll(\PDO::FETCH_COLUMN);
            $payloads[$id] = $row;
        }
        return $payloads[$id];
    }
}
