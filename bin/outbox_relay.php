<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Messaging\RabbitMQMessageBroker;
use App\Infrastructure\Persistence\SqliteConnection;
use App\Infrastructure\Persistence\SqliteOutboxRepository;

const POLL_INTERVAL_SECONDS = 2;
const BATCH_SIZE = 50;

function buildBroker(): RabbitMQMessageBroker
{
    return new RabbitMQMessageBroker(
        host: getenv('RABBITMQ_HOST') ?: 'localhost',
        port: (int) (getenv('RABBITMQ_PORT') ?: 5672),
        user: getenv('RABBITMQ_USER') ?: 'guest',
        password: getenv('RABBITMQ_PASSWORD') ?: 'guest',
    );
}

function relayOnce(SqliteOutboxRepository $outbox, RabbitMQMessageBroker $broker): int
{
    $events = $outbox->fetchUnpublished(BATCH_SIZE);

    foreach ($events as $event) {
        try {
            $broker->publish($event['event_type'], $event['payload']);
            $outbox->markPublished($event['id']);
            echo "[outbox-relay] Event #{$event['id']} published successfully.\n";
        } catch (\Throwable $e) {
            fwrite(STDERR, "[outbox-relay] Failed to publish event #{$event['id']}: {$e->getMessage()}\n");
        }
    }

    return count($events);
}

echo "[outbox-relay] Starting publish loop (polling every " . POLL_INTERVAL_SECONDS . "s)...\n";

$pdo = SqliteConnection::make();
$outbox = new SqliteOutboxRepository($pdo);
$broker = buildBroker();

while (true) {
    relayOnce($outbox, $broker);
    sleep(POLL_INTERVAL_SECONDS);
}
