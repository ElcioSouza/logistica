<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Contracts\MessageBrokerInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

final class RabbitMQMessageBroker implements MessageBrokerInterface
{
    private ?AMQPStreamConnection $connection = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $password
    ) {}

    public function publish(string $queue, array $payload): void
    {
        $channel = $this->channel();
        RabbitMQTopology::declare($channel);

        $message = new AMQPMessage(
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]
        );

        $channel->basic_publish($message, RabbitMQTopology::MAIN_EXCHANGE, RabbitMQTopology::ROUTING_KEY);
        $channel->close();
    }

    private function channel(): \PhpAmqpLib\Channel\AMQPChannel
    {
        if ($this->connection === null || !$this->connection->isConnected()) {
            $this->connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password);
        }

        return $this->connection->channel();
    }

    public function __destruct()
    {
        if ($this->connection !== null && $this->connection->isConnected()) {
            $this->connection->close();
        }
    }
}
