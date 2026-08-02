<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Contracts\OutboxRepositoryInterface;
use PDO;
use RuntimeException;
use Throwable;

final class SqliteOutboxRepository implements OutboxRepositoryInterface
{
    public function __construct(
        private readonly PDO $connection
    ) {}

    public function recordUpload(string $filePath, string $receivedAt, string $eventType, array $eventPayload): int
    {
        try {
            $this->connection->beginTransaction();

            $insertUpload = $this->connection->prepare(
                'INSERT INTO uploads (file_path, status, received_at) VALUES (:file_path, :status, :received_at)'
            );
            $insertUpload->execute([
                'file_path' => $filePath,
                'status' => 'pending',
                'received_at' => $receivedAt,
            ]);

            $uploadId = (int) $this->connection->lastInsertId();

            $insertEvent = $this->connection->prepare(
                'INSERT INTO outbox_events (aggregate_type, aggregate_id, event_type, payload, created_at)
                 VALUES (:aggregate_type, :aggregate_id, :event_type, :payload, :created_at)'
            );
            $insertEvent->execute([
                'aggregate_type' => 'upload',
                'aggregate_id' => $uploadId,
                'event_type' => $eventType,
                'payload' => json_encode(['upload_id' => $uploadId] + $eventPayload, JSON_UNESCAPED_UNICODE),
                'created_at' => $receivedAt,
            ]);

            $this->connection->commit();

            return $uploadId;
        } catch (Throwable $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw new RuntimeException('Falha ao registrar upload no Outbox: ' . $e->getMessage(), 0, $e);
        }
    }

    public function fetchUnpublished(int $limit = 50): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, event_type, payload FROM outbox_events
             WHERE published_at IS NULL
             ORDER BY id ASC
             LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $row) => [
            'id' => (int) $row['id'],
            'event_type' => $row['event_type'],
            'payload' => json_decode($row['payload'], true),
        ], $rows);
    }

    public function markPublished(int $eventId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE outbox_events SET published_at = :published_at WHERE id = :id'
        );
        $statement->execute([
            'published_at' => date('Y-m-d H:i:s'),
            'id' => $eventId,
        ]);
    }

    public function updateUploadStatus(int $uploadId, string $status, ?int $processedLines = null, ?int $discardedLines = null): void
    {
        $statement = $this->connection->prepare(
            'UPDATE uploads
             SET status = :status, processed_at = :processed_at, processed_lines = :processed_lines, discarded_lines = :discarded_lines
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'processed_at' => date('Y-m-d H:i:s'),
            'processed_lines' => $processedLines,
            'discarded_lines' => $discardedLines,
            'id' => $uploadId,
        ]);
    }
}
