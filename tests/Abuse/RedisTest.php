<?php

namespace Utopia\Tests;

use DateInterval;
use Utopia\Abuse\Adapter;
use Utopia\Abuse\Abuse;
use Utopia\Abuse\Adapters\Redis;
use Redis as Client;
use Utopia\Http\Exception;

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
        $adapter = new Redis('login-attempt-from-{{ip}}', 3, 60 * 5, $this->redis);
        $adapter->setParam('{{ip}}', '127.0.0.1');
        $this->abuse = new Abuse($adapter);
    }

    public function getAdapter(string $key, int $limit, int $seconds): Adapter
    {
        return new Redis($key, $limit, $seconds, $this->redis);
    }

    public function getCleanupDateTime(): string
    {
        $interval = DateInterval::createFromDateString(1 . ' seconds');
        return strval((new \DateTime())->sub($interval)->getTimestamp());
    }
}
