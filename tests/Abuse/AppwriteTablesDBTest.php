<?php

namespace Utopia\Tests;

use Appwrite\Client;
use Utopia\Abuse\Adapters\TimeLimit;
use Utopia\Abuse\Adapters\TimeLimit\AppwriteTablesDB as AdapterAppwriteTablesDB;

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
            ->setEndpoint('https://fra.cloud.appwrite.io/v1')
            ->setProject('68f0b93e003404ce2e31') // Utopia PHP
            ->setKey('standard_6e7fe493f9dbe734c77eb701982baa223bc95149b61496306ce9e03276e0f79112cd8738a178d78cc5c66f0ae8ad912a71260086f1ab6b5c18271be3c58f66c9b5e3cca22a470a220093c585a5c5b24831c3fdac6ee8fdda7b19a5a63316bf45cbb30d9bc7e84a5e2580fac24acb6273fd13d86e2b2cf830276df9afd43f59f2');

        $adapter = new AdapterAppwriteTablesDB('', 1, 1, self::$client, self::$databaseId);
        $adapter->setup();
    }

    public function getAdapter(string $key, int $limit, int $seconds): TimeLimit
    {
        return new AdapterAppwriteTablesDB($key, $limit, $seconds, self::$client, self::$databaseId);
    }

    public static function tearDownAfterClass(): void
    {
    }
}
