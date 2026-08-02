<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure;

use App\Infrastructure\Persistence\SqliteOutboxRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class SqliteOutboxRepositoryTest extends TestCase
{
    private PDO $pdo;
    private SqliteOutboxRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec((string) file_get_contents(dirname(__DIR__, 3) . '/database/schema.sql'));

        $this->repository = new SqliteOutboxRepository($this->pdo);
    }

    public function testRecordUploadPersistsBothTheUploadAndTheOutboxEventAtomically(): void
    {
        $uploadId = $this->repository->recordUpload(
            filePath: '/var/www/storage/uploads/upload_abc.txt',
            receivedAt: '2026-08-01 10:00:00',
            eventType: 'file.uploaded',
            eventPayload: ['file_path' => '/var/www/storage/uploads/upload_abc.txt']
        );

        $this->assertIsInt($uploadId);
        $this->assertGreaterThan(0, $uploadId);

        $upload = $this->pdo->query('SELECT * FROM uploads WHERE id = ' . $uploadId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('pending', $upload['status']);

        $event = $this->pdo->query('SELECT * FROM outbox_events WHERE aggregate_id = ' . $uploadId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('file.uploaded', $event['event_type']);
        $this->assertNull($event['published_at']);
    }

    public function testFetchUnpublishedReturnsOnlyEventsNotYetPublished(): void
    {
        $this->repository->recordUpload('/a.txt', '2026-08-01 10:00:00', 'file.uploaded', ['file_path' => '/a.txt']);
        $this->repository->recordUpload('/b.txt', '2026-08-01 10:01:00', 'file.uploaded', ['file_path' => '/b.txt']);

        $pending = $this->repository->fetchUnpublished();

        $this->assertCount(2, $pending);
        $this->assertSame('file.uploaded', $pending[0]['event_type']);
    }

    public function testMarkPublishedRemovesTheEventFromTheUnpublishedList(): void
    {
        $this->repository->recordUpload('/a.txt', '2026-08-01 10:00:00', 'file.uploaded', ['file_path' => '/a.txt']);
        $pending = $this->repository->fetchUnpublished();
        $eventId = $pending[0]['id'];

        $this->repository->markPublished($eventId);

        $this->assertCount(0, $this->repository->fetchUnpublished());
    }

    public function testUpdateUploadStatusPersistsProgress(): void
    {
        $uploadId = $this->repository->recordUpload('/a.txt', '2026-08-01 10:00:00', 'file.uploaded', ['file_path' => '/a.txt']);

        $this->repository->updateUploadStatus($uploadId, 'processed', processedLines: 1000, discardedLines: 3);

        $upload = $this->pdo->query('SELECT * FROM uploads WHERE id = ' . $uploadId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('processed', $upload['status']);
        $this->assertSame(1000, (int) $upload['processed_lines']);
        $this->assertSame(3, (int) $upload['discarded_lines']);
    }
}
