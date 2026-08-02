<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Messaging\RabbitMQTopology;
use PHPUnit\Framework\TestCase;

class RabbitMQTopologyTest extends TestCase
{
    public function testMainConstantsAreCorrectlyDefined(): void
    {
        $this->assertSame('logistics', RabbitMQTopology::MAIN_EXCHANGE);
        $this->assertSame('q.uploads', RabbitMQTopology::MAIN_QUEUE);
        $this->assertSame('file.uploaded', RabbitMQTopology::ROUTING_KEY);
    }

    public function testRetryConstantsAreCorrectlyDefined(): void
    {
        $this->assertSame('logistics.retry', RabbitMQTopology::RETRY_EXCHANGE);
        $this->assertSame('q.uploads.retry', RabbitMQTopology::RETRY_QUEUE);
        $this->assertSame(10000, RabbitMQTopology::RETRY_TTL_MS);
    }

    public function testDlxConstantsAreCorrectlyDefined(): void
    {
        $this->assertSame('logistics.dlx', RabbitMQTopology::DLX_EXCHANGE);
        $this->assertSame('q.uploads.dlq', RabbitMQTopology::DLQ_QUEUE);
    }

    public function testMaxRetriesIsThree(): void
    {
        $this->assertSame(3, RabbitMQTopology::MAX_RETRIES);
    }

    public function testRetryCountReturnsZeroWhenNoHeaders(): void
    {
        $this->assertSame(0, RabbitMQTopology::retryCount([]));
    }

    public function testRetryCountReturnsZeroWhenNoDeathHeaders(): void
    {
        $this->assertSame(0, RabbitMQTopology::retryCount(['x-death' => []]));
    }

    public function testRetryCountReturnsZeroWhenNoRetryQueueInDeaths(): void
    {
        $headers = [
            'x-death' => [
                ['queue' => 'q.uploads.dlq', 'count' => 2],
            ],
        ];

        $this->assertSame(0, RabbitMQTopology::retryCount($headers));
    }

    public function testRetryCountReturnsCorrectCountForRetryQueue(): void
    {
        $headers = [
            'x-death' => [
                ['queue' => 'q.uploads.dlq', 'count' => 1],
                ['queue' => 'q.uploads.retry', 'count' => 3],
            ],
        ];

        $this->assertSame(3, RabbitMQTopology::retryCount($headers));
    }

    public function testRetryCountReturnsZeroWhenCountKeyIsMissing(): void
    {
        $headers = [
            'x-death' => [
                ['queue' => 'q.uploads.retry'],
            ],
        ];

        $this->assertSame(0, RabbitMQTopology::retryCount($headers));
    }

    public function testDeclareSetsUpCorrectTopology(): void
    {
        $channel = $this->createMock(\PhpAmqpLib\Channel\AMQPChannel::class);

        $channel->expects($this->exactly(3))
            ->method('exchange_declare');

        $channel->expects($this->exactly(3))
            ->method('queue_declare');

        $channel->expects($this->exactly(3))
            ->method('queue_bind');

        RabbitMQTopology::declare($channel);
    }
}
