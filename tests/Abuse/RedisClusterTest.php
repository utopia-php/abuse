<?php

namespace Utopia\Tests;

use DateInterval;
use RedisCluster;
use Utopia\Abuse\Abuse;
use Utopia\Abuse\Adapter;
use Utopia\Abuse\Adapters\TimeLimit\RedisCluster as TimeLimitRedisCluster;
use Utopia\Exception;

class RedisClusterTest extends Base
{
    protected RedisCluster $redis;

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function setUp(): void
    {
        $this->redis = new RedisCluster(null, ['redis-cluster-0:6379', 'redis-cluster-1:6379', 'redis-cluster-2:6379', 'redis-cluster-3:6379']);
        $adapter = new TimeLimitRedisCluster('login-attempt-from-{{ip}}', 3, 1, $this->redis);
        $adapter->setParam('{{ip}}', '127.0.0.1');
        $this->abuse = new Abuse($adapter);
        $this->abuse->cleanup($this->getCleanupDateTime());
    }

    public function getAdapter(string $key, int $limit, int $seconds): Adapter
    {
        return new TimeLimitRedisCluster($key, $limit, $seconds, $this->redis);
    }

    public function getCleanupDateTime(): string
    {
        $interval = DateInterval::createFromDateString(1 . ' seconds');
        return strval((new \DateTime())->sub($interval)->getTimestamp());
    }
}
