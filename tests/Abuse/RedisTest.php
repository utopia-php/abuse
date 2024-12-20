<?php

namespace Utopia\Tests;

use Redis;
use Utopia\Abuse\Adapters\TimeLimit;
use Utopia\Abuse\Adapters\TimeLimit\Redis as AdapterRedis;
use Utopia\Exception;

class RedisTest extends Base
{
    protected static ?\Redis $redis = null;

    /**
     * @throws Exception
     * @throws \Exception
     */
    public static function setUpBeforeClass(): void
    {
        if (self::$redis === null) {
            self::$redis = self::initialiseRedis();
        }
    }

    private static function initialiseRedis(): \Redis
    {
        $redis = new \Redis();
        $redis->connect('redis', 6379);
        return $redis;
    }

    public function getAdapter(string $key, int $limit, int $seconds): TimeLimit
    {
        return new AdapterRedis($key, $limit, $seconds, self::$redis);
    }

    /**
     * Clean up Redis connection after all tests
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$redis !== null) {
            self::$redis->close();
            self::$redis = null;
        }
    }
}
