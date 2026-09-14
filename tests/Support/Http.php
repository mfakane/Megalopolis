<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Support;

final class Http
{
    private static array $responses = [];

    public static function json(string $service, string $path): mixed
    {
        if (getenv('COMPAT_HTTP') !== '1') {
            throw new \RuntimeException('Integration tests require: bash compat/test.sh');
        }
        // No cookie jar: every request is a fresh visitor, identically on both versions.
        $context = stream_context_create(['http' => [
            'timeout' => 60, 'ignore_errors' => true, 'follow_location' => 0,
            'header' => "Host: compat.test\r\nAccept: application/json\r\nAccept-Encoding: identity\r\nUser-Agent: Megalopolis-compat-test\r\n",
        ]]);
        $body = file_get_contents('http://' . $service . ':8080' . $path, false, $context);
        $headers = $http_response_header ?? [];
        self::$responses[$service] = ['headers' => $headers, 'body' => $body];
        $type = '';
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $type = trim(substr($header, 13));
            }
        }
        if ($body === false || !preg_match('~^HTTP/\S+ 200(?: |$)~', $headers[0] ?? '')
            || !preg_match('~^application/json(?:;|$)~i', $type)) {
            self::artifact($service . '-' . $path, ['headers' => $headers, 'body' => $body]);
            throw new \RuntimeException($service . $path . ': expected HTTP 200 application/json, got '
                . implode('; ', $headers) . "\n" . substr((string) $body, 0, 1000));
        }
        try {
            return json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            self::artifact($service . '-' . $path, ['headers' => $headers, 'body' => $body]);
            throw $error;
        }
    }

    public static function response(string $service): array
    {
        return self::$responses[$service];
    }

    public static function artifact(string $name, mixed $value): void
    {
        $directory = is_dir('/results') ? '/results' : dirname(__DIR__, 2) . '/test-results';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $name);
        file_put_contents($directory . '/' . $name . '.json', json_encode($value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
    }
}
