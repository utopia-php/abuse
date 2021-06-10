<?php
/**
 * Utopia PHP Framework
 *
 * @package Abuse
 * @subpackage Tests
 *
 * @link https://github.com/utopia-php/framework
 * @author Eldad Fux <eldad@appwrite.io>
 * @version 1.0 RC4
 * @license The MIT License (MIT) <http://www.opensource.org/licenses/mit-license.php>
 */

namespace Utopia\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Utopia\Abuse\Abuse;
use Utopia\Abuse\Adapters\TimeLimit;
use Utopia\Cache\Adapter\None as NoCache;
use Utopia\Cache\Cache;
use Utopia\Database\Adapter\MySQL;
use Utopia\Database\Database;

class AbuseTest extends TestCase
{
    /**
     * @var Abuse
     */
    protected $abuse = null;

    public function setUp(): void
    {
        // Limit login attempts to 3 time in 5 minutes time frame
        $dbHost = '127.0.0.1';
        $dbUser = 'travis';
        $dbPass = '';

        $pdo = new PDO("mysql:host={$dbHost};", $dbUser, $dbPass, array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            PDO::ATTR_TIMEOUT => 5, // Seconds
        ));

        // Connection settings
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // Return arrays
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $db = new Database(new MySQL($pdo), new Cache(new NoCache()));
        $db->setNamespace('namespace');

        $adapter = new TimeLimit('login-attempt-from-{{ip}}', 3, (60 * 5), $db);
        if(!$db->exists()) {
            $db->create();
            $adapter->setup();
        }

        $adapter->setParam('{{ip}}', '127.0.0.1');

        $this->abuse = new Abuse($adapter);
    }

    public function tearDown(): void
    {
        $this->abuse = null;
    }

    public function testIsValid()
    {
        // Use vars to resolve adapter key
        $this->assertEquals($this->abuse->check(), false);
        $this->assertEquals($this->abuse->check(), false);
        $this->assertEquals($this->abuse->check(), false);
        $this->assertEquals($this->abuse->check(), true);
    }

    public function testCleanup()
    {

        // Check that there is only one log
        $logs = $this->abuse->getLogs(0, 10);
        $this->assertEquals(1, \count($logs));

        sleep(5);
        // Delete the log
        $status = $this->abuse->cleanup(time() - 1);
        $this->assertEquals($status, true);

        // Check that there are no logs in the DB
        $logs = $this->abuse->getLogs(0, 10);
        $this->assertEquals(0, \count($logs));
    }
}
