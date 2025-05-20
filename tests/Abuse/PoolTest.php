<?php

namespace Utopia\Tests;

use Utopia\Abuse\Adapters\TimeLimit\Pool;
use Utopia\Abuse\Adapters\TimeLimit\Redis;
use Utopia\Abuse\Adapters\TimeLimit;

class PoolTest extends RedisTest
{
    /**
     * @var \Utopia\Pools\Pool<covariant Redis> $pool
     */
    protected static \Utopia\Pools\Pool $pool;
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();


        self::$pool = new \Utopia\Pools\Pool('test', 10, function () {
            $redis = RedisTest::initialiseRedis();
            return new Redis('', 10, 60, $redis);
        });
    }

    public function getAdapter(string $key, int $limit, int $seconds): TimeLimit
    {
        return new Pool($key, $limit, $seconds, self::$pool);
    }
}
