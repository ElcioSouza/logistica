<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use PhpAmqpLib\Channel\AMQPChannel;

final class RabbitMQTopology
{
    public const MAIN_EXCHANGE = 'logistics';
    public const MAIN_QUEUE = 'q.uploads';
    public const ROUTING_KEY = 'file.uploaded';

    public const RETRY_EXCHANGE = 'logistics.retry';
    public const RETRY_QUEUE = 'q.uploads.retry';
    public const RETRY_TTL_MS = 10000;

    public const DLX_EXCHANGE = 'logistics.dlx';
    public const DLQ_QUEUE = 'q.uploads.dlq';

    public const MAX_RETRIES = 3;

    public static function declare(AMQPChannel $channel): void
    {
    
        $channel->exchange_declare(self::DLX_EXCHANGE, 'direct', false, true, false);
        $channel->queue_declare(self::DLQ_QUEUE, false, true, false, false);
        $channel->queue_bind(self::DLQ_QUEUE, self::DLX_EXCHANGE, self::ROUTING_KEY);

     
        $channel->exchange_declare(self::RETRY_EXCHANGE, 'direct', false, true, false);
        $channel->queue_declare(self::RETRY_QUEUE, false, true, false, false, false, [
            'x-message-ttl' => ['I', self::RETRY_TTL_MS],
            'x-dead-letter-exchange' => ['S', self::MAIN_EXCHANGE],
            'x-dead-letter-routing-key' => ['S', self::ROUTING_KEY],
        ]);
        $channel->queue_bind(self::RETRY_QUEUE, self::RETRY_EXCHANGE, self::ROUTING_KEY);

        $channel->exchange_declare(self::MAIN_EXCHANGE, 'direct', false, true, false);
        $channel->queue_declare(self::MAIN_QUEUE, false, true, false, false, false, [
            'x-dead-letter-exchange' => ['S', self::RETRY_EXCHANGE],
            'x-dead-letter-routing-key' => ['S', self::ROUTING_KEY],
        ]);
        $channel->queue_bind(self::MAIN_QUEUE, self::MAIN_EXCHANGE, self::ROUTING_KEY);
    }

    public static function retryCount(array $headers): int
    {
        $deaths = $headers['x-death'] ?? [];
        foreach ($deaths as $death) {
            if (($death['queue'] ?? null) === self::RETRY_QUEUE) {
                return (int) ($death['count'] ?? 0);
            }
        }

        return 0;
    }
}
