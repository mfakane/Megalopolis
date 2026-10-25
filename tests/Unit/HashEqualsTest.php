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
}
