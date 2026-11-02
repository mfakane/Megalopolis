<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\App;
use Megalopolis\UpdateCheck;
use PHPUnit\Framework\TestCase;

final class UpdateCheckTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/megalopolis-update-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        @unlink($this->directory . '/' . UpdateCheck::CACHE_FILE);
        @rmdir($this->directory);
    }

    private static function release(string $tag, string $url = 'https://github.com/mfakane/Megalopolis/releases/tag/r99', array $extra = []): string
    {
        return json_encode($extra + ['tag_name' => $tag, 'html_url' => $url, 'draft' => false, 'prerelease' => false], JSON_THROW_ON_ERROR);
    }

    public function testParsesReleaseTag(): void
    {
        self::assertSame(
            ['version' => 99, 'url' => 'https://github.com/mfakane/Megalopolis/releases/tag/r99'],
            UpdateCheck::parseRelease(self::release('r99')),
        );
    }

    public function testIgnoresCandidatesAndPrereleases(): void
    {
        self::assertNull(UpdateCheck::parseRelease(self::release('rc99')));
        self::assertNull(UpdateCheck::parseRelease(self::release('r99', extra: ['prerelease' => true])));
        self::assertNull(UpdateCheck::parseRelease(self::release('r99', extra: ['draft' => true])));
        self::assertNull(UpdateCheck::parseRelease('{"message":"API rate limit exceeded"}'));
        self::assertNull(UpdateCheck::parseRelease('not json'));
    }

    public function testFallsBackToReleasesPageForUnexpectedUrl(): void
    {
        foreach (['javascript:alert(1)', 'https://example.com/r99', 'https://github.com/a" onclick="x'] as $url)
            self::assertSame(UpdateCheck::RELEASES_URL, UpdateCheck::parseRelease(self::release('r99', $url))['url'] ?? null, $url);
    }

    public function testReportsOnlyNewerVersions(): void
    {
        $newer = App::VERSION + 1;
        self::assertSame($newer, UpdateCheck::getNewerRelease(fn() => self::release("r{$newer}"), $this->directory, 1000)['version'] ?? null);

        @unlink($this->directory . '/' . UpdateCheck::CACHE_FILE);
        self::assertNull(UpdateCheck::getNewerRelease(fn() => self::release('r' . App::VERSION), $this->directory, 1000));
    }

    public function testChecksAtMostOncePerDay(): void
    {
        $calls = 0;
        $fetch = function () use (&$calls) {
            $calls++;

            return self::release('r' . (App::VERSION + $calls));
        };

        self::assertSame(App::VERSION + 1, UpdateCheck::getNewerRelease($fetch, $this->directory, 1000)['version'] ?? null);
        self::assertSame(App::VERSION + 1, UpdateCheck::getNewerRelease($fetch, $this->directory, 1000 + UpdateCheck::INTERVAL - 1)['version'] ?? null);
        self::assertSame(1, $calls);

        self::assertSame(App::VERSION + 2, UpdateCheck::getNewerRelease($fetch, $this->directory, 1000 + UpdateCheck::INTERVAL)['version'] ?? null);
        self::assertSame(2, $calls);
    }

    public function testFailureAlsoWaitsADay(): void
    {
        $calls = 0;
        $fetch = function () use (&$calls) {
            $calls++;

            return null;
        };

        self::assertNull(UpdateCheck::getNewerRelease($fetch, $this->directory, 1000));
        self::assertNull(UpdateCheck::getNewerRelease($fetch, $this->directory, 2000));
        self::assertSame(1, $calls);
        self::assertStringStartsWith(UpdateCheck::CACHE_FILE_HEADER, (string)file_get_contents($this->directory . '/' . UpdateCheck::CACHE_FILE));
    }

    public function testUnwritableDirectoryShowsNothing(): void
    {
        self::assertNull(UpdateCheck::getNewerRelease(fn() => self::fail('should not fetch'), $this->directory . '/missing', 1000));
    }
}
