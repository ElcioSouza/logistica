<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure;

use App\Infrastructure\Persistence\SqliteConnection;
use PDO;
use PHPUnit\Framework\TestCase;

class SqliteConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SqliteConnection::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        SqliteConnection::reset();
    }

    public function testMakeReturnsAPdoInstance(): void
    {
        $pdo = SqliteConnection::make(':memory:');

        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function testMakeReturnsSameInstanceOnSubsequentCalls(): void
    {
        $first = SqliteConnection::make(':memory:');
        $second = SqliteConnection::make(':memory:');

        $this->assertSame($first, $second);
    }

    public function testResetClearsTheSingletonInstance(): void
    {
        $first = SqliteConnection::make(':memory:');
        SqliteConnection::reset();
        $second = SqliteConnection::make(':memory:');

        $this->assertNotSame($first, $second);
    }

    public function testSchemaCreatesUploadsTable(): void
    {
        $pdo = SqliteConnection::make(':memory:');

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='uploads'")->fetch();

        $this->assertNotEmpty($tables);
    }

    public function testSchemaCreatesOutboxEventsTable(): void
    {
        $pdo = SqliteConnection::make(':memory:');

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='outbox_events'")->fetch();

        $this->assertNotEmpty($tables);
    }

    public function testForeignKeysAreEnabled(): void
    {
        $pdo = SqliteConnection::make(':memory:');

        $result = $pdo->query('PRAGMA foreign_keys')->fetchColumn();

        $this->assertEquals(1, $result);
    }

    public function testWalJournalModeIsEnabledWhenUsingFile(): void
    {
        $tmpFile = sys_get_temp_dir() . '/test_sqlite_' . uniqid() . '.db';

        $pdo = SqliteConnection::make($tmpFile);

        $result = $pdo->query('PRAGMA journal_mode')->fetchColumn();

        $this->assertSame('wal', $result);

        SqliteConnection::reset();
        @unlink($tmpFile);
    }

    public function testMakeWithCustomPathCreatesDatabaseFile(): void
    {
        $tmpFile = sys_get_temp_dir() . '/test_sqlite_custom_' . uniqid() . '.db';

        $pdo = SqliteConnection::make($tmpFile);

        $this->assertInstanceOf(PDO::class, $pdo);
        $this->assertFileExists($tmpFile);

        SqliteConnection::reset();
        @unlink($tmpFile);
    }
}
