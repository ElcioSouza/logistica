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

    public function handle(Request $request): Response
    {
        
        $normalized = [];
        foreach ($request->queryParams as $k => $v) {
            $key = strtolower(trim(str_replace(' ', '_', (string) $k)));
            $normalized[$key] = is_string($v) ? trim($v) : $v;
        }

        $orderId = $normalized['order_id'] ?? null;
        $startDate = $normalized['start_date'] ?? null;
        $endDate = $normalized['end_date'] ?? null;

        $page = max(1, (int) ($normalized['page'] ?? 1));
        $perPage = (int) ($normalized['per_page'] ?? 50);
        $perPage = min(500, max(1, $perPage));

  
        if ($startDate && !$endDate) {
            $endDate = $startDate;
        }

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

        $payload = $this->getOrdersUseCase->execute($orderId, $startDate, $endDate, $page, $perPage);

        if (isset($payload['data']) && isset($payload['meta'])) {
            $meta = $payload['meta'];
            $headers = [
                'X-Page' => (string) $meta['page'],
                'X-Per-Page' => (string) $meta['per_page'],
                'X-Total' => (string) $meta['total'],
                'X-Total-Pages' => (string) $meta['total_pages'],
            ];

            return new Response($payload['data'], 200, $headers);
        }

        return new Response($payload, 200);
    }
}