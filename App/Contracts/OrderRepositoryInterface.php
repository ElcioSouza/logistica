<?php
 

declare(strict_types=1);

namespace App\Contracts;

/**
 * ================================================================================================
 * INTERFACE OrderRepositoryInterface — Contrato de persistência do domínio Pedido
 * ================================================================================================
 *
 * PRINCÍPIO SOLID APLICADO — Dependency Inversion Principle (DIP):
 *   Os UseCases (GetOrdersUseCase, worker de ingestão) dependem desta abstração, nunca da
 *   implementação concreta (RedisOrderRepository). Trocar a tecnologia de armazenamento
 *   significa apenas criar uma nova classe que implemente esta interface.
 *
 * MODELO DE DADOS ESPERADO (payload por usuário):
 *   [
 *     'user_id' => 1,
 *     'name'    => 'Zarelli',
 *     'orders'  => [
 *       [
 *         'order_id' => 123,
 *         'date'     => '2021-12-01',
 *         'total'    => 1024.48,
 *         'products' => [
 *           ['product_id' => 111, 'value' => 512.24],
 *           ['product_id' => 122, 'value' => 512.24],
 *         ],
 *       ],
 *     ],
 *   ]
 * ================================================================================================
 */
interface OrderRepositoryInterface
{
    
    public function findByOrderId(string $orderId): ?array;

    public function findByDateRange(string $startDate, string $endDate): array;
}
