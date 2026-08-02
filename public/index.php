<?php

declare(strict_types=1);

require_once __DIR__ . '../../vendor/autoload.php';

use App\Controllers\UploadController;
use App\Controllers\OrderController;
use App\Http\Request;
use App\Http\Router;
use App\UseCases\GetOrdersUseCase;
use App\Infrastructure\Persistence\RedisOrderRepository;
use Predis\Client;

$redis = new Client([
    'scheme' => 'tcp',
    'host' => getenv('REDIS_HOST') ?: 'localhost',
    'port' => (int) (getenv('REDIS_PORT') ?: 6379),
]);

$sqlite = SqliteConnection::make();

$orderRepository = new RedisOrderRepository($redis);
$outboxRepository = new SqliteOutboxRepository($sqlite);

$uploadFileUseCase = new UploadFileUseCase($outboxRepository);
$getOrdersUseCase = new GetOrdersUseCase($orderRepository);

$uploadController = new UploadController($uploadFileUseCase);
$orderController = new OrderController($getOrdersUseCase);

$router = new Router();
$router->register('POST', '/api/upload', $uploadController, 'handle');
$router->register('GET', '/api/orders', $orderController, 'handle');

$request = Request::createFromGlobals();

try {
    $response = $router->handle($request);
    $response->sendJson();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Internal server error']);
}
