<?php

declare(strict_types=1);

namespace App\Http;

class Response
{
    public function __construct(
        public readonly array $payload,
        public readonly int $statusCode = 200
    ) {}
    
    public function sendJson(): void
    {
        http_response_code($this->statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}