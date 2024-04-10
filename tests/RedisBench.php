<?php

use Redis as Client;
use Utopia\Abuse\Abuse;
use Utopia\Abuse\Adapters\Redis;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;

final class RedisBench
{
    protected Client $redis;
    protected Abuse $abuse;
    protected Redis $adapter;

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

    #[BeforeMethods('setUp')]
    #[Iterations(50000)]
    public function benchTimelimit(): void
    {
        $ip = '';
        for ($i = 0; $i < 4; $i++) {
            $sub = rand(0, 255);
            $ip .= $sub . '.';
        };
        $ip = ltrim($ip, '.');
        $this->adapter->setParam('{{ip}}', $ip);
        $this->abuse->check();
    }
}
