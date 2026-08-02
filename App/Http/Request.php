<?php

declare(strict_types=1);

namespace App\Http;


class Request
{

    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly array $queryParams,
        public readonly array $body,
        public readonly array $files
    ) {}


    public static function createFromGlobals(): self
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

        return new self(
            method: $_SERVER['REQUEST_METHOD'],
            uri: $uri,                            
            queryParams: $_GET,                   
            body: $_POST,                         
            files: $_FILES                        
        );
    }
}