<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Contracts\OrderRepositoryInterface;
use Predis\ClientInterface;

final class RedisOrderRepository implements OrderRepositoryInterface
{
    private const DATE_INDEX_KEY = 'orders_by_date';

    public function __construct(
        private readonly ClientInterface $redis
    ) {}

    public function upsertBatch(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $rowsByUser = [];
        foreach ($rows as $row) {
            $rowsByUser[$row['user_id']][] = $row;
        }

        $userIds = array_keys($rowsByUser);
        $userKeys = array_map(fn (int $id): string => $this->userKey($id), $userIds);

        $existingPayloads = $this->redis->mget($userKeys);

        foreach ($userIds as $index => $userId) {
            $existingJson = $existingPayloads[$index] ?? null;
            $user = $existingJson
                ? json_decode($existingJson, true)
                : ['user_id' => $userId, 'name' => null, 'orders' => []];

            foreach ($rowsByUser[$userId] as $row) {
                $user['name'] = $row['name'];
                $orderId = (string) $row['order_id'];

                if (!isset($user['orders'][$orderId])) {
                    $user['orders'][$orderId] = [
                        'order_id' => $row['order_id'],
                        'date' => $row['purchase_date'],
                        'total' => 0.0,
                        'products' => [],
                    ];
                }

                $user['orders'][$orderId]['products'][] = [
                    'product_id' => $row['product_id'],
                    'value' => $row['product_value'],
                ];
                $user['orders'][$orderId]['total'] = round(
                    $user['orders'][$orderId]['total'] + $row['product_value'],
                    2
                );

                $this->redis->set($this->orderIndexKey($orderId), (string) $userId);

                $dateScore = (int) str_replace('-', '', $row['purchase_date']);
                $this->redis->zadd(self::DATE_INDEX_KEY, [$orderId => $dateScore]);
            }

            $this->redis->set($this->userKey($userId), json_encode($user, JSON_UNESCAPED_UNICODE));
        }
    }

    public function findByOrderId(string $orderId): ?array
    {
        $userId = $this->redis->get($this->orderIndexKey($orderId));
        if (!$userId) {
            return null;
        }

        $userJson = $this->redis->get($this->userKey((int) $userId));
        if (!$userJson) {
            return null;
        }

        $user = json_decode($userJson, true);
        if (!isset($user['orders'][$orderId])) {
            return null;
        }

        return $this->buildUserPayload($user, [$orderId]);
    }

    public function findByDateRange(string $startDate, string $endDate): array
    {
        $minScore = (int) str_replace('-', '', $startDate);
        $maxScore = (int) str_replace('-', '', $endDate);

        $orderIds = $this->redis->zrangebyscore(self::DATE_INDEX_KEY, $minScore, $maxScore);
        if (empty($orderIds)) {
            return [];
        }

        $indexKeys = array_map(fn (string $orderId): string => $this->orderIndexKey($orderId), $orderIds);
        $owners = $this->redis->mget($indexKeys);

        $orderIdsByUser = [];
        foreach ($orderIds as $i => $orderId) {
            $userId = $owners[$i] ?? null;
            if ($userId !== null) {
                $orderIdsByUser[$userId][] = $orderId;
            }
        }

        if (empty($orderIdsByUser)) {
            return [];
        }

        $userIds = array_keys($orderIdsByUser);
        $userKeys = array_map(fn (string $id): string => $this->userKey((int) $id), $userIds);
        $userPayloads = $this->redis->mget($userKeys);

        $result = [];
        foreach ($userIds as $index => $userId) {
            $userJson = $userPayloads[$index] ?? null;
            if (!$userJson) {
                continue;
            }

            $user = json_decode($userJson, true);
            $result[] = $this->buildUserPayload($user, $orderIdsByUser[$userId]);
        }

        return $result;
    }

    private function buildUserPayload(array $user, array $onlyOrderIds): array
    {
        $orders = [];
        foreach ($onlyOrderIds as $orderId) {
            if (!isset($user['orders'][$orderId])) {
                continue;
            }

            $order = $user['orders'][$orderId];
            $order['products'] = array_values($order['products']);
            $orders[] = $order;
        }

        return [
            'user_id' => $user['user_id'],
            'name' => $user['name'],
            'orders' => $orders,
        ];
    }

    private function userKey(int $userId): string
    {
        return "user:{$userId}";
    }

    private function orderIndexKey(string $orderId): string
    {
        return "order_index:{$orderId}";
    }
}
