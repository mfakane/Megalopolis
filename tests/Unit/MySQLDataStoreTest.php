<?php
declare(strict_types=1);

namespace Megalopolis\Tests\Unit;

use Megalopolis\Configuration;
use Megalopolis\MySQLDataStore;
use Megalopolis\Visualizer;
use PHPUnit\Framework\TestCase;

final class MySQLDataStoreTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/req/Model/Board.php';
        require_once dirname(__DIR__, 2) . '/req/Core/DataStore.php';
        Configuration::$instance = new Configuration();
    }

    public function testUtf8TablesStoreSupplementaryCharactersAsReferences(): void
    {
        // Not connected yet: the store assumes legacy utf8 (3-byte) tables.
        $store = new MySQLDataStore('db', ['127.0.0.1', 3306], 'user', 'password');

        self::assertSame('絵文字&#128512; &#134071;野家 ☺', $store->encodeValue('絵文字😀 𠮷野家 ☺'));
        self::assertSame(42, $store->encodeValue(42));
        self::assertNull($store->encodeValue(null));
    }

    public function testStoredReferencesAreShownAsTheOriginalCharacters(): void
    {
        $store = new MySQLDataStore('db', ['127.0.0.1', 3306], 'user', 'password');

        self::assertSame('絵文字😀 &lt;b&gt;', Visualizer::escapeOutput($store->encodeValue('絵文字😀 <b>')));
    }
}
