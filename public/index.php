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


$uploadController = new UploadController();

$router = new Router();
$router->register('POST', '/api/upload', $uploadController, 'handle');

// Orders route and dependencies
$redis = new Client();
$orderRepository = new RedisOrderRepository($redis);
$getOrdersUseCase = new GetOrdersUseCase($orderRepository);
$orderController = new OrderController($getOrdersUseCase);
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
