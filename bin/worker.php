<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Messaging\RabbitMQTopology;
use App\Infrastructure\Persistence\RedisOrderRepository;
use App\Infrastructure\Persistence\SqliteConnection;
use App\Infrastructure\Persistence\SqliteOutboxRepository;
use App\UseCases\ProcessFileUseCase;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Predis\Client as RedisClient;

const BATCH_SIZE = 2000;

function connectWithRetry(int $maxAttempts = 10, int $delaySeconds = 3): AMQPStreamConnection
{
    $lastError = null;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            return new AMQPStreamConnection(
                getenv('RABBITMQ_HOST') ?: 'localhost',
                (int) (getenv('RABBITMQ_PORT') ?: 5672),
                getenv('RABBITMQ_USER') ?: 'guest',
                getenv('RABBITMQ_PASSWORD') ?: 'guest',
            );
        } catch (\Throwable $e) {
            $lastError = $e;
            echo "[worker] RabbitMQ unavailable (attempt {$attempt}/{$maxAttempts}), retrying in {$delaySeconds}s...\n";
            sleep($delaySeconds);
        }
    }

    throw new \RuntimeException('Unable to connect to RabbitMQ: ' . $lastError?->getMessage());
}

$connection = connectWithRetry();
$channel = $connection->channel();
RabbitMQTopology::declare($channel);
$channel->basic_qos(0, 1, false);

$redis = new RedisClient([
    'scheme' => 'tcp',
    'host' => getenv('REDIS_HOST') ?: 'localhost',
    'port' => (int) (getenv('REDIS_PORT') ?: 6379),
]);

$orderRepository = new RedisOrderRepository($redis);
$outboxRepository = new SqliteOutboxRepository(SqliteConnection::make());
$processor = new ProcessFileUseCase();

function sendToDeadLetterQueue(\PhpAmqpLib\Channel\AMQPChannel $channel, AMQPMessage $msg): void
{
    $deadMessage = new AMQPMessage($msg->getBody(), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
    $channel->basic_publish($deadMessage, RabbitMQTopology::DLX_EXCHANGE, RabbitMQTopology::ROUTING_KEY);
    $msg->ack();

    echo "[worker] Message exhausted retry attempts — moved to terminal DLQ.\n";
}

$onMessage = function (AMQPMessage $msg) use ($processor, $orderRepository, $outboxRepository) {
    $properties = $msg->get_properties();
    $headers = isset($properties['application_headers']) ? $properties['application_headers']->getNativeData() : [];
    $retries = RabbitMQTopology::retryCount($headers);

    if ($retries >= RabbitMQTopology::MAX_RETRIES) {
        sendToDeadLetterQueue($msg->getChannel(), $msg);
        return;
    }

    $payload = json_decode($msg->getBody(), true);
    $uploadId = $payload['upload_id'] ?? null;
    $filePath = $payload['file_path'] ?? null;

    if (!$filePath || !file_exists($filePath)) {
        fwrite(STDERR, "[worker] File not found: " . ($filePath ?? 'null') . " — sending to retry.\n");
        $msg->nack(false);
        return;
    }

    try {
        if ($uploadId) {
            $outboxRepository->updateUploadStatus((int) $uploadId, 'processing');
        }

        echo "[worker] Processing: {$filePath}\n";

        $processedLines = 0;
        $discardedLines = 0;
        $onError = static function (int $lineNumber, string $line, string $reason) use (&$discardedLines): void {
            $discardedLines++;
            error_log("[worker] Line {$lineNumber} discarded (corrupted): {$reason}");
        };

        $batch = [];
        foreach ($processor->execute($filePath, $onError) as $row) {
            $batch[] = $row;
            $processedLines++;

            if (count($batch) >= BATCH_SIZE) {
                $orderRepository->upsertBatch($batch);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            $orderRepository->upsertBatch($batch);
        }

        if ($uploadId) {
            $outboxRepository->updateUploadStatus((int) $uploadId, 'processed', $processedLines, $discardedLines);
        }

        @unlink($filePath);

        echo "[worker] Completed. Lines processed: {$processedLines}. Discarded: {$discardedLines}.\n";
        $msg->ack();
    } catch (\Throwable $e) {
        fwrite(STDERR, "[worker] Failed to process message: {$e->getMessage()} — sending to retry.\n");

        if ($uploadId) {
            $outboxRepository->updateUploadStatus((int) $uploadId, 'failed');
        }

        $msg->nack(false);
    }
};

$channel->basic_consume(RabbitMQTopology::MAIN_QUEUE, '', false, false, false, false, $onMessage);

echo "[worker] Waiting for messages on " . RabbitMQTopology::MAIN_QUEUE . "...\n";

while ($channel->is_consuming()) {
    $channel->wait();
}
