<?php

declare(strict_types=1);

namespace App\UseCases;

use App\Contracts\OrderRepositoryInterface;

class GetOrdersUseCase
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository
    ) {}

    public function execute(?string $orderId, ?string $startDate, ?string $endDate, int $page = 1, int $perPage = 50): array
    {
        if ($orderId !== null && $orderId !== '') {
            $order = $this->repository->findByOrderId($orderId);
            return $order ? [$order] : [];
        }

        return $this->repository->findByDateRange((string) $startDate, (string) $endDate, $page, $perPage);
    }
}
