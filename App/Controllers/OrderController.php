<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\UseCases\GetOrdersUseCase;

class OrderController
{
    private GetOrdersUseCase $getOrdersUseCase;

    public function __construct(GetOrdersUseCase $getOrdersUseCase)
    {
        $this->getOrdersUseCase = $getOrdersUseCase;
    }

    public function fetch(Request $request): Response
    {
        $orderId = $request->queryParams['order_id'] ?? null;   
        $startDate = $request->queryParams['start_date'] ?? null;  
        $endDate = $request->queryParams['end_date'] ?? null;      

        if (!$orderId && (!$startDate || !$endDate)) {
            return new Response([
                'error' => 'Bad Request. Provide either the order_id parameter or the start_date and end_date range.'
            ], 400);
        }

        $datePattern = '/^\d{4}-\d{2}-\d{2}$/';
        if ($startDate && !preg_match($datePattern, $startDate)) {
            return new Response(['error' => 'Invalid start_date. Use the yyyy-mm-dd format.'], 400);
        }
        if ($endDate && !preg_match($datePattern, $endDate)) {
            return new Response(['error' => 'Invalid end_date. Use the yyyy-mm-dd format.'], 400);
        }
        if ($startDate && $endDate && $startDate > $endDate) {
            return new Response(['error' => 'start_date cannot be later than end_date.'], 400);
        }

        $payload = $this->getOrdersUseCase->execute($orderId, $startDate, $endDate);

        return new Response([
            'data' => $payload
        ], 200);
    }
}