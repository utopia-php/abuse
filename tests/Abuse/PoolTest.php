<?php

namespace Utopia\Tests;

use Utopia\Abuse\Adapters\TimeLimit\Pool;
use Utopia\Abuse\Adapters\TimeLimit;

class PoolTest extends RedisTest
{
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
        return new Pool($key, $limit, $seconds, self::$pool);
    }
}
