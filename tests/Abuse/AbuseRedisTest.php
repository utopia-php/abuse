<?php

namespace Utopia\Tests;

use DateInterval;
use PHPUnit\Framework\TestCase;
use Utopia\Abuse\Abuse;
use Utopia\Abuse\Adapters\Redis;
use Redis as Client;
use Utopia\Exception;

class AbuseRedisTest extends TestCase
{
    protected Abuse $abuse;

    protected Abuse $abuseIp;

    protected Client $redis;

    protected string $format = 'Y-m-d H:i:s.v';

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

    public function tearDown(): void
    {
        unset($this->abuse);
    }

    public function testImitate2Requests(): void
    {
        $key = '{{ip}}';
        $value = '0.0.0.10';

        $adapter = new Redis($key, 1, 1, $this->redis);
        $adapter->setParam($key, $value);
        $this->abuseIp = new Abuse($adapter);
        $this->assertEquals($this->abuseIp->check(), false);
        $this->assertEquals($this->abuseIp->check(), true);

        sleep(1);

        $adapter = new Redis($key, 1, 1, $this->redis);
        $adapter->setParam($key, $value);
        $this->abuseIp = new Abuse($adapter);

        $this->assertEquals($this->abuseIp->check(), false);
        $this->assertEquals($this->abuseIp->check(), true);
    }

    public function testIsValid(): void
    {
        // Use vars to resolve adapter key
        $this->assertEquals($this->abuse->check(), false);
        $this->assertEquals($this->abuse->check(), false);
        $this->assertEquals($this->abuse->check(), false);
        $this->assertEquals($this->abuse->check(), true);
    }

    public function testCleanup(): void
    {
        // Check that there is only one log
        $logs = $this->abuse->getLogs(0, 10);
        $this->assertEquals(3, \count($logs));

        // Delete the log
        $interval = DateInterval::createFromDateString(1 . ' seconds');
        $status = $this->abuse->cleanup((new \DateTime())->sub($interval)->getTimestamp());
        $this->assertEquals($status, true);

        // Check that there are no logs in the DB
        $logs = $this->abuse->getLogs(0, 10);
        $this->assertEquals(0, \count($logs));
    }
}
