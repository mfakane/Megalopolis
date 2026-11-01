<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Configuration;
use Megalopolis\Util;
use PHPUnit\Framework\TestCase;

final class HashEqualsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/req/Model/Board.php';
        Configuration::$instance = new Configuration();
    }

    public function testZeroIsAUsablePassword(): void
    {
        self::assertNotFalse(Util::hashEquals(Util::hash('0'), '0'));
        self::assertFalse(Util::hashEquals(Util::hash('0'), '1'));
    }

    public function testEmptyInputsNeverMatch(): void
    {
        self::assertFalse(Util::hashEquals(Util::hash('key'), ''));
        self::assertFalse(Util::hashEquals(Util::hash('key'), false));
        // Rows without a stored hash must not accept "0" (empty("0") is true in PHP).
        self::assertFalse(Util::hashEquals('', '0'));
        self::assertFalse(Util::hashEquals('', 'anything'));
    }

    public function testNewHashesUsePasswordHash(): void
    {
        $hash = Util::hash('key');

        self::assertStringStartsWith('$', $hash);
        self::assertTrue(password_verify('key', $hash));
        self::assertSame(Util::HASH_TYPE_MEGALOPOLIS2, Util::hashEquals($hash, 'key'));
        self::assertSame(Util::HASH_TYPE_LATEST, Util::hashEquals($hash, 'key'));
        self::assertFalse(Util::hashEquals($hash, 'other'));
        self::assertFalse(Util::hashNeedsRehash($hash));
    }

    public function testLegacyHashesStillVerifyAndNeedRehash(): void
    {
        $legacy = array(
            Util::HASH_TYPE_MEGALOPOLIS1 => Util::hash('key', 1),
            Util::HASH_TYPE_MEGALITH => sha1('key'),
            Util::HASH_TYPE_ANTHOLOGYS => crypt('key', 'ab'),
        );

        foreach ($legacy as $type => $hash) {
            self::assertSame($type, Util::hashEquals($hash, 'key'), $type);
            self::assertFalse(Util::hashEquals($hash, 'other'), $type);
            self::assertTrue(Util::hashNeedsRehash($hash), $type);
        }
    }

    public function testMegalithHashIsNotComparedLoosely(): void
    {
        // sha1("aaroZmOk") is a "magic hash" (0e followed by digits); loose comparison would match any other 0e hash.
        self::assertTrue('0e00000000000000000000000000000000000000' == sha1('aaroZmOk'));
        self::assertFalse(Util::hashEquals('0e00000000000000000000000000000000000000', 'aaroZmOk'));
    }
}
