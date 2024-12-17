<?php

namespace Utopia\Tests;

use DateInterval;
use Redis as Client;
use Utopia\Abuse\Abuse;
use Utopia\Abuse\Adapter;
use Utopia\Abuse\Adapters\TimeLimit\Redis as TimeLimitRedis;
use Utopia\Exception;

class RedisTest extends Base
{
    protected Client $redis;

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function setUp(): void
    {
        $this->redis = new Client();
        $this->redis->connect('redis', 6379);
        $adapter = new TimeLimitRedis('login-attempt-from-{{ip}}', 3, 1, $this->redis);
        $adapter->setParam('{{ip}}', '127.0.0.1');
        $this->abuse = new Abuse($adapter);
        $this->abuse->cleanup($this->getCleanupDateTime());
    }

    public function getAdapter(string $key, int $limit, int $seconds): Adapter
    {
        return new TimeLimitRedis($key, $limit, $seconds, $this->redis);
    }

    public function getCleanupDateTime(): string
    {
        $interval = DateInterval::createFromDateString(1 . ' seconds');
        return strval((new \DateTime())->sub($interval)->getTimestamp());
    }
}
