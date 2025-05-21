<?php

namespace Utopia\Tests;

use Utopia\Abuse\Adapters\TimeLimit\PoolRedis;
use Utopia\Abuse\Adapters\TimeLimit;

class PoolRedisTest extends RedisTest
{
    /**
     * @var \Utopia\Pools\Pool<covariant \Redis> $pool
     */
    protected static \Utopia\Pools\Pool $pool;
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();


        self::$pool = new \Utopia\Pools\Pool('test', 10, function () {
            $redis = RedisTest::initialiseRedis();
            return $redis;
        });
    }

    public function getAdapter(string $key, int $limit, int $seconds): TimeLimit
    {
        return new PoolRedis($key, $limit, $seconds, self::$pool);
    }
}
