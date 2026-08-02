<?php

declare(strict_types=1);

namespace App\Http;

class Router
{
    private array $routes = [];

    public function register(string $httpMethod, string $routePath, object $controllerInstance, string $handlerMethod): void
    {
        $this->routes["{$httpMethod}|{$routePath}"] = [
            'controller' => $controllerInstance,
            'handler' => $handlerMethod          
        ];
    }
    public function handle(Request $request): Response
    {
        $routeKey = "{$request->method}|{$request->uri}";

        if (!isset($this->routes[$routeKey])) {
            return new Response(['error' => 'Endpoint not found'], 404);
        }

        $route = $this->routes[$routeKey];
        $controller = $route['controller'];
        $handler = $route['handler'];      

        return $controller->$handler($request);
    }
}