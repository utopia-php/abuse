<?php

namespace Utopia\Tests;

use PHPUnit\Framework\TestCase;
use Utopia\Abuse\Abuse;
use Utopia\Abuse\Adapters\TimeLimit\RedisPool;
use Utopia\CircuitBreaker\CircuitBreaker;
use Utopia\Pools\Adapter\Stack;
use Utopia\Pools\Pool;

class RedisPoolCircuitBreakerTest extends TestCase
{
    public function testCircuitBreakerFailsOpenWhenPoolUnavailable(): void
    {
        $pool = new Pool(new Stack(), 'abuse-redis-unavailable', 1, function (): \Redis {
            throw new \Exception('Redis unavailable');
        });
        $pool
            ->setReconnectAttempts(1)
            ->setRetryAttempts(1);

        /** @var Pool<\Redis> $pool */
        $adapter = new RedisPool(
            'redis-pool-unavailable',
            1,
            60,
            $pool,
            new CircuitBreaker()
        );

        $abuse = new Abuse($adapter);

        $this->assertFalse($abuse->check());
        $this->assertSame([], $adapter->getLogs());
    }
}
