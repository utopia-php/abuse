<?php

namespace Utopia\Tests;

use PHPUnit\Framework\TestCase;
use Utopia\Abuse\Abuse;
use Utopia\Abuse\Adapter;

abstract class Base extends TestCase
{
    protected Abuse $abuse;

    protected Abuse $abuseIp;

    abstract public function getAdapter(string $key, int $limit, int $seconds): Adapter;

    abstract public function getCleanupDateTime(): string;

    public function tearDown(): void
    {
        unset($this->abuse);
    }

    public function testImitate2Requests(): void
    {
        $key = '{{ip}}';
        $value = '0.0.0.10';

        $adapter = $this->getAdapter($key, 1, 1);
        $adapter->setParam($key, $value);
        $this->abuseIp = new Abuse($adapter);
        $this->assertEquals($this->abuseIp->check(), false);
        $this->assertEquals($this->abuseIp->check(), true);

        sleep(1);

        $adapter = $this->getAdapter($key, 1, 1);
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

        sleep(5);
        // Delete the log

        $status = $this->abuse->cleanup($this->getCleanupDateTime());
        $this->assertEquals($status, true);

        // Check that there are no logs in the DB
        $logs = $this->abuse->getLogs(0, 10);
        $this->assertEquals(0, \count($logs));
    }
}
