<?php

namespace Utopia\Tests;

use Appwrite\Client;
use Utopia\Abuse\Adapters\TimeLimit;
use Utopia\Abuse\Adapters\TimeLimit\Appwrite\TablesDB;

class AppwriteTablesDBTest extends Base
{
    protected static Client $client;
    protected static string $databaseId;

    public static function setUpBeforeClass(): void
    {
        if (isset(self::$client)) {
            return;
        }

        self::initialiseDatabase();
    }

    private static function initialiseDatabase(): void
    {
        self::$databaseId = 'abuse-cicd-' . \uniqid();
        self::$client = (new Client())
            ->setEndpoint(\getenv('APPWRITE_ENDPOINT') ?: '')
            ->setProject(\getenv('APPWRITE_PROJECT_ID') ?: '')
            ->setKey(\getenv('APPWRITE_API_KEY') ?: '');

        $adapter = new TablesDB('', 1, 1, self::$client, self::$databaseId);
        $adapter->setup();
    }

    public function getAdapter(string $key, int $limit, int $seconds): TimeLimit
    {
        return new TablesDB($key, $limit, $seconds, self::$client, self::$databaseId);
    }

    public static function tearDownAfterClass(): void
    {
    }
}
