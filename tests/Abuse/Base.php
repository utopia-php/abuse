<?php

namespace Utopia\Tests;

use PHPUnit\Framework\TestCase;
use Utopia\Abuse\Abuse;
use Utopia\Abuse\Adapters\TimeLimit;

abstract class Base extends TestCase
{
    abstract public function getAdapter(string $key, int $limit, int $seconds): TimeLimit;

    /**
     * Test a static key with a limit of 2 requests per second
     */
    public function testStaticKey(): void
    {
        $adapter = $this->getAdapter('static-key', 2, 1);
        $abuse = new Abuse($adapter);
        $this->assertEquals($abuse->check(), false);
        $this->assertEquals($abuse->check(), false);
        $this->assertEquals($abuse->check(), true);
    }

    /**
     * Test a dynamic key with a limit of 2 requests per second
     */
    public function testDynamicKey(): void
    {
        $adapter = $this->getAdapter('dynamic-key-{{ip}}', 2, 1);
        $adapter->setParam('{{ip}}', '0.0.0.10');
        $abuse = new Abuse($adapter);
        $this->assertEquals($abuse->check(), false);
        $this->assertEquals($abuse->check(), false);
        $this->assertEquals($abuse->check(), true);
    }

    /**
     * Test a dynamic key with 2 params
     */
    public function testDynamicKeyWith2Params(): void
    {
        $adapter = $this->getAdapter('two-params-{{ip}}-{{email}}', 2, 1);
        $adapter->setParam('{{ip}}', '0.0.0.10');
        $adapter->setParam('{{email}}', 'test@test.com');
        $abuse = new Abuse($adapter);
        $this->assertEquals($abuse->check(), false);
        $this->assertEquals($abuse->check(), false);
        $this->assertEquals($abuse->check(), true);
    }

    /**
     * Test a dynamic key with higher request rate like 100 requests per second
     */
    public function testDynamicKeyFastRequests(): void
    {
        $adapter = $this->getAdapter('fast-requests-{{ip}}', 100, 1);
        $adapter->setParam('{{ip}}', '0.0.0.10');
        $abuse = new Abuse($adapter);
        for ($i = 0; $i < 100; $i++) {
            $this->assertEquals($abuse->check(), false);
        }
        $this->assertEquals($abuse->check(), true);
    }

    /**
     * Test that the limit is reset after the time limit
     */
    public function testLimitReset(): void
    {
        $adapter = $this->getAdapter('limit-reset-{{ip}}', 100, 2);
        $adapter->setParam('{{ip}}', '127.0.0.1');
        $abuse = new Abuse($adapter);
        for ($i = 0; $i < 100; $i++) {
            $this->assertEquals($abuse->check(), false);
        }
        $this->assertEquals($abuse->check(), true);

        // Wait for the limit to reset
        sleep(2);

        /** Seems to be a bug in the code where if use the same adapter, it caches the result of the previous check */
        $adapter = $this->getAdapter('limit-reset-{{ip}}', 100, 1);
        $adapter->setParam('{{ip}}', '127.0.0.1');
        $abuse = new Abuse($adapter);
        $this->assertEquals($abuse->check(), false);
    }

    /**
     * Verify that the time format is correct
     */
    public function testTimeFormat(): void
    {
        $now = time();
        $adapter = $this->getAdapter('', 1, 1);
        $this->assertEquals($adapter->time(), $now);
        $this->assertEquals(true, \is_int($adapter->time()));
    }
}
