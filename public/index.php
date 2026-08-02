<?php
declare(strict_types=1);

require_once __DIR__ . '../../vendor/autoload.php';

use App\Controllers\UploadController;
use App\Http\Request;
use App\Http\Router;

$uploadController = new UploadController();

$router = new Router();
$router->register('POST', '/api/upload', $uploadController, 'handle');

$request = Request::createFromGlobals();

try {
    $response = $router->handle($request);
    $response->sendJson();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Internal server error']);
}
