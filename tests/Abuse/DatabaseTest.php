<?php

namespace Utopia\Tests;

use PDO;
use Utopia\Abuse\Abuse;
use Utopia\Abuse\Adapter;
use Utopia\Abuse\Adapters\Database\TimeLimit as AdaptersDatabase;
use Utopia\Cache\Adapter\None as NoCache;
use Utopia\Cache\Cache;
use Utopia\Database\Adapter\MariaDB;
use Utopia\Database\Adapter\MySQL;
use Utopia\Database\Database;
use Utopia\Database\DateTime;
use Utopia\Http\Exception;

class DatabaseTest extends Base
{
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
        $this->db = $db;

        $adapter = new AdaptersDatabase('login-attempt-from-{{ip}}', 3, 60 * 5, $db);
        if (! $db->exists('utopiaTests')) {
            $db->create();
            $adapter->setup();
        }

        $adapter->setParam('{{ip}}', '127.0.0.1');
        $this->abuse = new Abuse($adapter);
    }

    public function getAdapter(string $key, int $limit, int $seconds): Adapter
    {
        return new AdaptersDatabase($key, $limit, $seconds, $this->db);
    }

    public function getCleanupDateTime(): string
    {
        return DateTime::addSeconds(new \DateTime(), -1);
    }
}
