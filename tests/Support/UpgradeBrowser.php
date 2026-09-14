<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Support;

/** A cookie-preserving HTTP client. All writes go through the real controller. */
final class UpgradeBrowser
{
    private array $cookies = [];
    private static int $sequence = 0;

    public function __construct(private string $service)
    {
        if (getenv('COMPAT_HTTP') !== '1' || getenv('COMPAT_UPGRADE') !== '1') {
            throw new \RuntimeException('Upgrade tests require: bash compat/upgrade.sh');
        }
        if (!preg_match('/^(baseline|candidate|restored)-(sqlite|mysql)$/', $service)) {
            throw new \InvalidArgumentException('Not an isolated upgrade service');
        }
    }

    public function request(string $method, string $route, array $fields = []): array
    {
        $query = ['path' => $route] + ($method === 'GET' ? $fields : []);
        $headers = "Host: compat.test\r\nAccept: application/json, text/html\r\n"
            . "User-Agent: Megalopolis-upgrade-test\r\nAccept-Language: ja\r\n"
            . "Referer: http://compat.test/index.php\r\n";
        if ($this->cookies !== []) {
            $headers .= 'Cookie: ' . implode('; ', array_map(
                fn($name, $value) => $name . '=' . $value, array_keys($this->cookies), $this->cookies)) . "\r\n";
        }
        $options = ['method' => $method, 'timeout' => 60, 'ignore_errors' => true,
            'follow_location' => 0, 'header' => $headers];
        if ($method === 'POST') {
            $options['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $options['content'] = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
        }
        $url = 'http://' . $this->service . ':8080/index.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $body = file_get_contents($url, false, stream_context_create(['http' => $options]));
        $lines = $http_response_header ?? [];
        $status = preg_match('~^HTTP/\S+ (\d{3})~', $lines[0] ?? '', $match) ? (int)$match[1] : 0;
        $response = ['status' => $status, 'headers' => $lines, 'body' => $body];
        Http::artifact('upgrade-' . $this->service . '-' . (++self::$sequence),
            ['request' => ['method' => $method, 'route' => $route, 'fields' => $fields], 'response' => $response]);
        if ($body === false || $status === 0) {
            throw new \RuntimeException('No HTTP response: ' . $url);
        }
        foreach ($lines as $header) {
            if (preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i', $header, $cookie)) {
                if ($cookie[2] === '') unset($this->cookies[$cookie[1]]);
                else $this->cookies[$cookie[1]] = $cookie[2];
            }
            if (preg_match('/^Content-Type:\s*application\/json(?:;|$)/i', $header)) {
                $response['json'] = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            }
            if (stripos($header, 'Location:') === 0) {
                $response['location'] = trim(substr($header, 9));
            }
        }
        return $response;
    }

    public function snapshot(?int $id = null): array
    {
        $response = $this->request('GET', '', ['upgradeSnapshot' => '1'] + ($id === null ? [] : ['id' => $id]));
        if ($response['status'] !== 200 || !isset($response['json']['works'])) {
            throw new \RuntimeException('Snapshot failed: ' . substr($response['body'], 0, 1500));
        }
        return $response['json'];
    }

    public function token(): string
    {
        $response = $this->request('GET', 'new');
        if ($response['status'] !== 200) {
            throw new \RuntimeException('Authentication form did not load');
        }
        $document = \Dom\HTMLDocument::createFromString($response['body'], LIBXML_NOERROR, 'UTF-8');
        $input = $document->querySelector('input[name="token"]');
        if ($input === null || $input->getAttribute('value') === '') {
            throw new \RuntimeException('No CSRF token in the real authentication form');
        }
        return $input->getAttribute('value');
    }
}
