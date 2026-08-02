<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Messaging\RabbitMQMessageBroker;
use PHPUnit\Framework\TestCase;

class RabbitMQMessageBrokerTest extends TestCase
{
    public function testConstructorStoresCredentials(): void
    {
        $broker = new RabbitMQMessageBroker('localhost', 5672, 'guest', 'guest');

        $reflection = new \ReflectionClass($broker);

        $host = $reflection->getProperty('host');
        $this->assertSame('localhost', $host->getValue($broker));

        $port = $reflection->getProperty('port');
        $this->assertSame(5672, $port->getValue($broker));

        $user = $reflection->getProperty('user');
        $this->assertSame('guest', $user->getValue($broker));

        $password = $reflection->getProperty('password');
        $this->assertSame('guest', $password->getValue($broker));
    }

    public function testConstructorWithDifferentCredentials(): void
    {
        $broker = new RabbitMQMessageBroker('rabbitmq.example.com', 5673, 'admin', 'secret123');

        $reflection = new \ReflectionClass($broker);

        $host = $reflection->getProperty('host');
        $this->assertSame('rabbitmq.example.com', $host->getValue($broker));

        $port = $reflection->getProperty('port');
        $this->assertSame(5673, $port->getValue($broker));

        $user = $reflection->getProperty('user');
        $this->assertSame('admin', $user->getValue($broker));

        $password = $reflection->getProperty('password');
        $this->assertSame('secret123', $password->getValue($broker));
    }

    public function testConnectionIsNullByDefault(): void
    {
        $broker = new RabbitMQMessageBroker('localhost', 5672, 'guest', 'guest');

        $reflection = new \ReflectionClass($broker);
        $connection = $reflection->getProperty('connection');

        $this->assertNull($connection->getValue($broker));
    }

    public function testImplementsMessageBrokerInterface(): void
    {
        $broker = new RabbitMQMessageBroker('localhost', 5672, 'guest', 'guest');

        $this->assertInstanceOf(\App\Contracts\MessageBrokerInterface::class, $broker);
    }

    public function testPublishMethodExists(): void
    {
        $broker = new RabbitMQMessageBroker('localhost', 5672, 'guest', 'guest');

        $this->assertTrue(method_exists($broker, 'publish'));
    }
}
