<?php

declare(strict_types=1);

namespace App\UseCases;

use App\Contracts\OrderRepositoryInterface;

final class GetOrdersUseCase
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository
    ) {}

    public function execute(?string $orderId, ?string $startDate, ?string $endDate): array
    {
        if ($orderId !== null && $orderId !== '') {
            $order = $this->repository->findByOrderId($orderId);
            return $order ? [$order] : [];
        }

        return $this->repository->findByDateRange((string) $startDate, (string) $endDate);
    }
}
