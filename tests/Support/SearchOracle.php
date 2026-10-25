<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Support;

/**
 * Which works a search must return, computed from the stored titles, names and
 * tags rather than from the search index. Terms are plain substrings; a leading
 * "-" excludes. The index lower-cases (and MySQL folds width and accents), so
 * included terms are matched exactly and excluded terms case-insensitively:
 * every work found here must also be found by the application.
 */
final class SearchOracle
{
    public const int PAGING = 30;

    /** @return array<int, array{title: string, name: string, tags: string[]}> */
    public static function works(\PDO $db): array
    {
        $works = [];
        foreach ($db->query('SELECT id, title, name FROM threadEntry') as $row) {
            $works[(int)$row['id']] = ['title' => (string)$row['title'], 'name' => (string)$row['name'], 'tags' => []];
        }
        foreach ($db->query('SELECT id, tag FROM threadTag') as $row) {
            if (isset($works[(int)$row['id']])) {
                $works[(int)$row['id']]['tags'][] = (string)$row['tag'];
            }
        }
        return $works;
    }

    /** The candidate's database as stored, for drivers the runner can reach. */
    public static function candidate(string $driver): \PDO
    {
        if (Drivers::fixture($driver) === 'sqlite') {
            return Fixtures::sqliteOracle();
        }
        return self::mysql($driver . '-candidate');
    }

    public static function mysql(string $host): \PDO
    {
        return new \PDO('mysql:host=' . $host . ';dbname=megalopolis_r46;charset=utf8mb4', 'fixture', 'fixture',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
    }

    /**
     * @param array<string, string> $query search.json parameters (title, name, tag)
     * @return int[]
     */
    public static function expected(array $works, array $query): array
    {
        $ids = [];
        foreach ($works as $id => $work) {
            foreach ($query as $field => $terms) {
                $values = $field === 'tag' ? $work['tags'] : [$work[$field]];
                foreach (explode(' ', $terms) as $term) {
                    $exclude = str_starts_with($term, '-');
                    $matches = $exclude
                        ? array_filter($values, fn($x) => str_contains(mb_strtolower($x), mb_strtolower(substr($term, 1))))
                        : array_filter($values, fn($x) => str_contains($x, $term));
                    if ($exclude === ($matches !== [])) {
                        continue 3;
                    }
                }
            }
            $ids[] = $id;
        }
        sort($ids);
        return $ids;
    }

    /**
     * Every page of search.json; each page must agree with the page count.
     * @return int[]
     */
    public static function found(Browser $browser, array $query): array
    {
        $ids = [];
        $pageCount = 1;
        for ($page = 1; $page <= $pageCount; $page++) {
            $response = $browser->request('GET', 'search.json', $query + ['p' => $page]);
            if ($response['status'] !== 200 || !isset($response['json']['entries'])) {
                throw new \RuntimeException('search.json failed: ' . substr((string)$response['body'], 0, 1000));
            }
            $pageCount = $response['json']['pageCount'];
            $entries = array_column($response['json']['entries'], 'id');
            if ($pageCount === 0 && $entries === []) {
                break;
            }
            if ($page < $pageCount ? count($entries) !== self::PAGING : $entries === []) {
                throw new \RuntimeException(sprintf('Page %d of %d has %d entries', $page, $pageCount, count($entries)));
            }
            array_push($ids, ...$entries);
        }
        if (count($ids) !== count(array_unique($ids))) {
            throw new \RuntimeException('The same work is listed twice');
        }
        sort($ids);
        return $ids;
    }
}
