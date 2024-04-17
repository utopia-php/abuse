<?php

namespace Utopia\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Utopia\Abuse\Abuse;
use Utopia\Abuse\Adapters\TimeLimit;
use Utopia\Cache\Adapter\None as NoCache;
use Utopia\Cache\Cache;
use Utopia\Database\Adapter\MariaDB;
use Utopia\Database\Adapter\MySQL;
use Utopia\Database\Database;
use Utopia\Database\DateTime;
use Utopia\Database\Exception;
use Utopia\Database\Validator\Authorization;

class AbuseTest extends TestCase
{
    protected Abuse $abuse;

    protected Abuse $abuseIp;

    protected Database $db;

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function setUp(): void
    {
        // Limit login attempts to 3 time in 5 minutes time frame
        $dbHost = 'mysql';
        $dbUser = 'root';
        $dbPort = '3306';
        $dbPass = 'password';

        $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, MariaDB::getPdoAttributes());

        $db = new Database(new MySQL($pdo), new Cache(new NoCache()));
        $db->setDatabase('utopiaTests');
        $db->setNamespace('namespace');

        $auth = new Authorization();
        $db->setAuthorization($auth);
        $this->db = $db;

        // todo: Should I create a new Authorization() or can I use $auth from the db?
        $adapter = new TimeLimit('login-attempt-from-{{ip}}', 3, 60 * 5, $db, $auth);
        if (! $db->exists('utopiaTests')) {
            $db->create();
            $adapter->setup();
        }

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

        $adapter = new TimeLimit($key, 1, 1, $this->db, new Authorization());
        $adapter->setParam($key, $value);
        $this->abuseIp = new Abuse($adapter);
        $this->assertEquals($this->abuseIp->check(), false);
        $this->assertEquals($this->abuseIp->check(), true);

        sleep(1);

        $adapter = new TimeLimit($key, 1, 1, $this->db, new Authorization());
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
        $status = $this->abuse->cleanup(DateTime::addSeconds(new \DateTime(), -1));
        $this->assertEquals($status, true);

        // Check that there are no logs in the DB
        $logs = $this->abuse->getLogs(0, 10);
        $this->assertEquals(0, \count($logs));
    }
}
