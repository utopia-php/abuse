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

class AbuseTest extends TestCase
{
    /**
     * @var Abuse
     */
    protected $abuse = null;

    public function setUp(): void
    {
        // Limit login attempts to 3 time in 5 minutes time frame
        $dbHost = 'mysql';
        $dbUser = 'root';
        $dbPort = '3306';
        $dbPass = 'password';

        $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, MariaDB::getPdoAttributes());

        $db = new Database(new MySQL($pdo), new Cache(new NoCache()));
        $db->setDefaultDatabase('utopiaTests');
        $db->setNamespace('namespace');

        $adapter = new TimeLimit('login-attempt-from-{{ip}}', 3, (60 * 5), $db);
        if (! $db->exists('utopiaTests')) {
            $db->create('utopiaTests');
            $adapter->setup();
        }

        $adapter->setParam('{{ip}}', '127.0.0.1');

        $this->abuse = new Abuse($adapter);
    }

    public function tearDown(): void
    {
        unset($this->abuse);
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
        $this->assertEquals(1, \count($logs));

        sleep(5);
        // Delete the log
        $status = $this->abuse->cleanup(DateTime::addSeconds(new \DateTime(), -1));
        $this->assertEquals($status, true);

        // Check that there are no logs in the DB
        $logs = $this->abuse->getLogs(0, 10);
        $this->assertEquals(0, \count($logs));
    }
}
