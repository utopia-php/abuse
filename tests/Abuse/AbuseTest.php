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
use Utopia\Abuse\Abuse;
use Utopia\Abuse\Adapters\TimeLimit;

use PHPUnit\Framework\TestCase;

class AbuseTest extends TestCase
{
    /**
     * @var Abuse
     */
    protected $abuse = null;

    public function setUp()
    {
        // Limit login attempts to 3 time in 5 minutes time frame
        $adapter = new TimeLimit('login-attempt-from-{{ip}}', 3, (60 * 5), function () {
            $dbHost = '127.0.0.1';
            $dbUser = 'travis';
            $dbPass = '';
            $dbName = 'abuse';

            $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName}", $dbUser, $dbPass, array(
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                PDO::ATTR_TIMEOUT => 5 // Seconds
            ));

            // Connection settings
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);   // Return arrays
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);        // Handle all errors with exceptions

            return $pdo;
        });

        $adapter
            ->setNamespace('namespace') // DB table namespace
            ->setParam('{{ip}}', '127.0.0.1')
        ;

        $this->abuse = new Abuse($adapter);
    }

    public function tearDown()
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
}
