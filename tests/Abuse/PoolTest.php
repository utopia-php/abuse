<?php

namespace Utopia\Tests;

use Utopia\Abuse\Adapters\TimeLimit;
use Utopia\Abuse\Adapters\TimeLimit\Pool as AdapterPool;
use Utopia\Pools\Adapter\Stack;
use Utopia\Pools\Pool;

class PoolTest extends Base
{
    /**
     * @var Pool<\Redis>|null
     */
    protected static ?Pool $pool = null;

    public static function setUpBeforeClass(): void
    {
        if (isset(self::$pool)) {
            return;
        }

        self::$pool = new Pool(new Stack(), 'abuse-redis', 2, function (): \Redis {
            $redis = new \Redis();
            $redis->connect('redis', 6379);

            return $redis;
        });
    }

    public function getAdapter(string $key, int $limit, int $seconds): TimeLimit
    {
        $pool = self::$pool;
        $this->assertInstanceOf(Pool::class, $pool);

        /** @var Pool<\Redis> $pool */
        return new AdapterPool($key, $limit, $seconds, $pool);
    }

    public static function tearDownAfterClass(): void
    {
        if (!isset(self::$pool)) {
            return;
        }

        self::$pool->use(function (mixed $redis): void {
            if ($redis instanceof \Redis) {
                $redis->close();
            }
        });
        self::$pool = null;
    }
}
