<?php

namespace Utopia\Tests\Bench;

use Redis as Client;
use Utopia\Abuse\Abuse;
use Utopia\Abuse\Adapters\Redis;

final class RedisBench extends Base
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
        $this->adapter = new Redis('login-attempt-from-{{ip}}', 3, 60 * 5, $this->redis);
        $this->abuse = new Abuse($this->adapter);
    }
}
