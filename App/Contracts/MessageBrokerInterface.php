<?php

declare(strict_types=1);

namespace App\Contracts;

interface MessageBrokerInterface
{
    public function publish(string $queue, array $payload): void;
}