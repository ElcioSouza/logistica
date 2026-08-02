<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Persistence\RedisOrderRepository;
use PHPUnit\Framework\TestCase;
use Predis\Client;

class RedisOrderRepositoryTest extends TestCase
{

    private function mockRedisClient()
    {
        return $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['mget', 'get', 'set', 'zadd', 'zrangebyscore'])
            ->getMock();
    }

    public function testUpsertBatchAggregatesTwoProductsIntoTheSameOrderForTheSameUser(): void
    {
        $redis = $this->mockRedisClient();

        $redis->method('mget')->willReturn([null]);

        $capturedUserJson = null;
        $redis->expects($this->atLeastOnce())
            ->method('set')
            ->willReturnCallback(function (string $key, string $value) use (&$capturedUserJson) {
                if (str_starts_with($key, 'user:')) {
                    $capturedUserJson = $value;
                }
                return true;
            });

        $repository = new RedisOrderRepository($redis);

        $repository->upsertBatch([
            ['user_id' => 1, 'name' => 'Zarelli', 'order_id' => 123, 'product_id' => 111, 'product_value' => 512.24, 'purchase_date' => '2021-12-01'],
            ['user_id' => 1, 'name' => 'Zarelli', 'order_id' => 123, 'product_id' => 122, 'product_value' => 512.24, 'purchase_date' => '2021-12-01'],
        ]);

        $this->assertNotNull($capturedUserJson);
        $user = json_decode($capturedUserJson, true);

        $this->assertSame(1, $user['user_id']);
        $this->assertSame('Zarelli', $user['name']);
        $this->assertCount(1, $user['orders']);
        $this->assertCount(2, $user['orders']['123']['products']); 
        $this->assertEqualsWithDelta(1024.48, $user['orders']['123']['total'], 0.001);
    }

    public function testFindByOrderIdReturnsNullWhenIndexIsMissing(): void
    {
        $redis = $this->mockRedisClient();
        $redis->method('get')->willReturn(null);

        $repository = new RedisOrderRepository($redis);

        $this->assertNull($repository->findByOrderId('999'));
    }

    public function testFindByOrderIdReturnsOnlyTheMatchedOrder(): void
    {
        $redis = $this->mockRedisClient();

        $userPayload = json_encode([
            'user_id' => 1,
            'name' => 'Zarelli',
            'orders' => [
                '123' => ['order_id' => 123, 'date' => '2021-12-01', 'total' => 1024.48, 'products' => [
                    ['product_id' => 111, 'value' => 512.24],
                    ['product_id' => 122, 'value' => 512.24],
                ]],
            ],
        ]);

        $redis->method('get')->willReturnMap([
            ['order_index:123', '1'],
            ['user:1', $userPayload],
        ]);

        $repository = new RedisOrderRepository($redis);
        $result = $repository->findByOrderId('123');

        $this->assertNotNull($result);
        $this->assertSame(1, $result['user_id']);
        $this->assertCount(1, $result['orders']);
        $this->assertSame(123, $result['orders'][0]['order_id']);
    }

    public function testFindByDateRangeReturnsEmptyArrayWhenNoOrdersMatch(): void
    {
        $redis = $this->mockRedisClient();
        $redis->method('zrangebyscore')->willReturn([]);

        $repository = new RedisOrderRepository($redis);

        $this->assertSame([], $repository->findByDateRange('2021-01-01', '2021-01-31'));
    }
}
