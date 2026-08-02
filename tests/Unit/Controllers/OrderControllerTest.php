<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\OrderController;
use App\Http\Request;
use App\Http\Response;
use App\UseCases\GetOrdersUseCase;
use PHPUnit\Framework\TestCase;

class OrderControllerTest extends TestCase
{
    private GetOrdersUseCase $getOrdersUseCaseMock;
    private OrderController $controller;


    protected function setUp(): void
    {
        $this->getOrdersUseCaseMock = $this->createMock(GetOrdersUseCase::class);
        $this->controller = new OrderController($this->getOrdersUseCaseMock);
    }

    public function test_returns_400_when_no_filter_is_provided(): void
    {
        $request = new Request('GET', '/api/orders', [], [], []);

        $response = $this->controller->handle($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->statusCode);
        $this->assertEquals(
            'Bad Request. Provide either the order_id parameter or the start_date and end_date range.',
            $response->payload['error']
        );
    }

    public function test_returns_400_when_date_is_in_an_invalid_format(): void
    {
        $request = new Request('GET', '/api/orders', [
            'start_date' => '01-01-2023',
            'end_date' => '2023-01-31',
        ], [], []);

        $response = $this->controller->handle($request);

        $this->assertEquals(400, $response->statusCode);
        $this->assertArrayHasKey('error', $response->payload);
    }

    public function test_returns_400_when_start_date_is_after_end_date(): void
    {
        $request = new Request('GET', '/api/orders', [
            'start_date' => '2023-02-01',
            'end_date' => '2023-01-01',
        ], [], []);

        $response = $this->controller->handle($request);

        $this->assertEquals(400, $response->statusCode);
    }

    public function test_delegates_to_use_case_and_returns_200_when_order_id_is_provided(): void
    {
        $expectedPayload = [['user_id' => 1, 'name' => 'Zarelli', 'orders' => []]];

        $this->getOrdersUseCaseMock->expects($this->once())
            ->method('execute')
            ->with('12345', null, null)
            ->willReturn($expectedPayload);

        $request = new Request('GET', '/api/orders', ['order_id' => '12345'], [], []);

        $response = $this->controller->handle($request);

        $this->assertEquals(200, $response->statusCode);
        $this->assertEquals($expectedPayload, $response->payload['data']);
    }

    public function test_delegates_to_use_case_when_date_range_is_provided(): void
    {
        $this->getOrdersUseCaseMock->expects($this->once())
            ->method('execute')
            ->with(null, '2023-01-01', '2023-01-31')
            ->willReturn([]);

        $request = new Request('GET', '/api/orders', [
            'start_date' => '2023-01-01',
            'end_date' => '2023-01-31',
        ], [], []);

        $response = $this->controller->handle($request);

        $this->assertEquals(200, $response->statusCode);
        $this->assertArrayHasKey('data', $response->payload);
    }
}
