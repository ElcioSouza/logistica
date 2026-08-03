<?php
 

declare(strict_types=1);

namespace App\Contracts;


interface OrderRepositoryInterface
{
    
    public function findByOrderId(string $orderId): ?array;


    public function findByDateRange(string $startDate, string $endDate, int $page, int $perPage): array;
}
