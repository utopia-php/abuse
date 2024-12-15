<?php

namespace Utopia\Abuse\Adapters\Redis;

use RedisCluster as Client;
use Utopia\Abuse\Adapters\Redis\TimeLimit as RedisTimeLimit;

class TimeLimit extends RedisTimeLimit
{
    /**
     * @var Client
     */
    protected Client $redis;

    public function __construct(string $key, int $limit, int $seconds, Client $redis)
    {
        $this->redis = $redis;
        $this->key = $key;
        $time = (int) \date('U', (int) (\floor(\time() / $seconds)) * $seconds);
        $this->time = strval($time);
        $this->limit = $limit;
    }
}
