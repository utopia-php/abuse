<?php

namespace Abuse\Bench;

use RedisCluster as Client;
use Utopia\Abuse\Abuse;
use Utopia\Abuse\Adapters\TimeLimit\RedisCluster as TimeLimit;
use Utopia\Abuse\Adapters\TimeLimit\RedisCluster;
use Utopia\Tests\Bench\Base;

final class RedisClusterBench extends Base
{
    protected Client $redis;

    /**
     * @throws \Exception
     */
    public function setUp(): void
    {
        $this->redis = new Client(null, ['redis-cluster-0:6379', 'redis-cluster-1:6379', 'redis-cluster-2:6379', 'redis-cluster-3:6379']);
        $this->adapter = new RedisCluster('login-attempt-from-{{ip}}', 3, 60 * 5, $this->redis);
        $this->abuse = new Abuse($this->adapter);
    }
}
