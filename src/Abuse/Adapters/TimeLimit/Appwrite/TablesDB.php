<?php

namespace Utopia\Abuse\Adapters\TimeLimit\Appwrite;

use Appwrite\AppwriteException;
use Appwrite\Client;
use Appwrite\Enums\IndexType;
use Appwrite\ID;
use Appwrite\Query;
use Appwrite\Services\TablesDB as TablesDBService;
use Utopia\Abuse\Adapters\TimeLimit;
use Utopia\Database\Document;
use Utopia\Exception;

class TablesDB extends TimeLimit
{
    public const DATABASE_NAME = 'Utopia';
    // Datbaase ID configurable in constructor
    public const TABLE_NAME = 'abuse';
    public const TABLE_ID = 'Abuse';
    public const TABLE_LOCK = 'lock'; // Lock table created to allow performant check of setup

    protected TablesDBService $tablesDB;
    protected string $databaseId;
    protected ?int $count = null;

    public function __construct(string $key, int $limit, int $seconds, Client $client, string $databaseId)
    {
        $this->key = $key;
        $now = \time();
        $this->timestamp = (int)($now - ($now % $seconds));
        $this->limit = $limit;
        $this->tablesDB = new TablesDBService($client);
        $this->databaseId = $databaseId;
    }

    /**
     * @throws Exception|\Exception
     */
    public function setup(): void
    {
        if ($this->isSetupComplete()) {
            return;
        }

        $this->createDatabase();
        $this->createTable();
        $this->createColumns();
        $this->waitForResourcesReady('columns');
        $this->createIndexes();
        $this->waitForResourcesReady('indexes');
        $this->createLockTable();
    }

    protected function isSetupComplete(): bool
    {
        try {
            $this->tablesDB->getTable($this->databaseId, self::TABLE_LOCK);
            return true;
        } catch (\Throwable $err) {
            return false;
        }
    }

    protected function createDatabase(): void
    {
        $this->executeWithSilentError(
            fn () => $this->tablesDB->create($this->databaseId, self::DATABASE_NAME),
            'database_already_exists'
        );
    }

    protected function createTable(): void
    {
        $this->executeWithSilentError(
            fn () => $this->tablesDB->createTable($this->databaseId, self::TABLE_ID, self::TABLE_NAME),
            'table_already_exists'
        );
    }

    protected function createColumns(): void
    {
        $columns = [
            fn () => $this->tablesDB->createStringColumn($this->databaseId, self::TABLE_ID, 'key', 255, true),
            fn () => $this->tablesDB->createDatetimeColumn($this->databaseId, self::TABLE_ID, 'time', true),
            fn () => $this->tablesDB->createIntegerColumn($this->databaseId, self::TABLE_ID, 'count', true, 0, PHP_INT_MAX)
        ];

        foreach ($columns as $createColumnFunction) {
            $this->executeWithSilentError($createColumnFunction, 'column_already_exists');
        }
    }

    protected function createIndexes(): void
    {
        $indexes = [
            fn () => $this->tablesDB->createIndex($this->databaseId, self::TABLE_ID, 'unique1', IndexType::UNIQUE(), ['key', 'time']),
            fn () => $this->tablesDB->createIndex($this->databaseId, self::TABLE_ID, 'index2', IndexType::KEY(), ['time'])
        ];

        foreach ($indexes as $createIndexFunction) {
            $this->executeWithSilentError($createIndexFunction, 'index_already_exists');
        }
    }

    protected function waitForResourcesReady(string $resourceType): void
    {
        $attempts = 0;
        $maxAttempts = 15;

        while ($attempts < $maxAttempts) {
            $attempts++;

            $response = $resourceType === 'columns'
                ? $this->tablesDB->listColumns($this->databaseId, self::TABLE_ID, [Query::notEqual('status', 'available'), Query::limit(1)])
                : $this->tablesDB->listIndexes($this->databaseId, self::TABLE_ID, [Query::notEqual('status', 'available'), Query::limit(1)]);

            $resources = $response[$resourceType];
            $resources = \array_filter($resources, fn ($resource) => $resource['status'] !== 'available');

            if (\count($resources) === 0) {
                return;
            }

            \sleep(1);
        }

        throw new \Exception("Failed to setup {$resourceType}.");
    }

    protected function createLockTable(): void
    {
        $this->executeWithSilentError(
            fn () => $this->tablesDB->createTable($this->databaseId, self::TABLE_LOCK, name: self::TABLE_LOCK),
            'table_already_exists'
        );
    }

    protected function executeWithSilentError(callable $callback, string $allowedErrorType): void
    {
        try {
            $callback();
        } catch (AppwriteException $err) {
            if ($err->getType() !== $allowedErrorType) {
                throw $err;
            }
        }
    }

    /**
     * Check
     *
     * Checks if number of counts is bigger or smaller than current limit
     *
     * @param  string  $key
     * @param  int  $timestamp
     * @return int
     *
     * @throws \Exception
     */
    protected function count(string $key, int $timestamp): int
    {
        if (0 == $this->limit) { // No limit no point for counting
            return 0;
        }

        if (! \is_null($this->count)) { // Get fetched result
            return $this->count;
        }

        $timestamp = $this->toDateTime($timestamp);

        $response = $this->tablesDB->listRows($this->databaseId, self::TABLE_ID, [
            Query::equal('key', [$key]),
            Query::equal('time', [$timestamp]),
        ]);
        $rows = $response['rows'];

        $this->count = 0;

        if (\count($rows) === 1) { // Unique Index
            $count = $rows[0]['count'] ?? 0;
            if (\is_numeric($count)) {
                $this->count = intval($count);
            }
        }

        return $this->count;
    }

    /**
     * @param  string  $key
     * @param  int  $timestamp
     * @return void
     *
     * @throws \Throwable
     */
    protected function hit(string $key, int $timestamp): void
    {
        if (0 == $this->limit) { // No limit no point for counting
            return;
        }

        $timestamp = $this->toDateTime($timestamp);

        $response = $this->tablesDB->listRows($this->databaseId, self::TABLE_ID, [
            Query::equal('key', [$key]),
            Query::equal('time', [$timestamp]),
        ]);
        $rows = $response['rows'];
        $data = $rows[0] ?? null;

        if (\is_null($data)) {
            $data = [
                'key' => $key,
                'time' => $timestamp,
                'count' => 1,
            ];

            try {
                $this->tablesDB->createRow($this->databaseId, self::TABLE_ID, ID::unique(), $data);
            } catch (AppwriteException $err) {
                if ($err->getType() !== 'row_already_exists') {
                    throw $err;
                }

                $response = $this->tablesDB->listRows($this->databaseId, self::TABLE_ID, [
                    Query::equal('key', [$key]),
                    Query::equal('time', [$timestamp]),
                ]);
                $rows = $response['rows'];

                $data = $rows[0] ?? null;

                if (!is_null($data)) {
                    $count = $data['count'] ?? 0;
                    if (\is_numeric($count)) {
                        $this->count = intval($count);
                    }

                    $this->tablesDB->incrementRowColumn($this->databaseId, self::TABLE_ID, $data['$id'], 'count', 1);
                } else {
                    throw new \Exception('Document Not Found');
                }
            }
        } else {
            $this->tablesDB->incrementRowColumn($this->databaseId, self::TABLE_ID, $data['$id'], 'count', 1);
        }

        $this->count++;
    }

    /**
     * @param  string  $key
     * @param  int  $timestamp
     * @param  int  $value
     * @return void
     *
     * @throws \Throwable
     */
    protected function set(string $key, int $timestamp, int $value): void
    {
        $timestamp = $this->toDateTime($timestamp);

        $response = $this->tablesDB->listRows($this->databaseId, self::TABLE_ID, [
            Query::equal('key', [$key]),
            Query::equal('time', [$timestamp]),
        ]);
        $rows = $response['rows'];
        $data = $rows[0] ?? null;

        if (\is_null($data)) {
            $data = [
                'key' => $key,
                'time' => $timestamp,
                'count' => $value,
            ];

            try {
                $this->tablesDB->createRow($this->databaseId, self::TABLE_ID, ID::unique(), $data);
            } catch (AppwriteException $err) {
                if ($err->getType() !== 'row_already_exists') {
                    throw $err;
                }

                $response = $this->tablesDB->listRows($this->databaseId, self::TABLE_ID, [
                    Query::equal('key', [$key]),
                    Query::equal('time', [$timestamp]),
                ]);
                $rows = $response['rows'];

                $data = $rows[0] ?? null;

                if (!is_null($data)) {
                    $this->tablesDB->updateRow($this->databaseId, self::TABLE_ID, $data['$id'], ['count' => $value]);
                } else {
                    throw new \Exception('Unable to find abuse tracking row after race condition handling');
                }
            }
        } else {
            $this->tablesDB->updateRow($this->databaseId, self::TABLE_ID, $data['$id'], ['count' => $value]);
        }

        $this->count = $value;
    }

    /**
     * Get abuse logs
     *
     * Return logs with an optional offset and limit
     *
     * @param  int|null  $offset
     * @param  int|null  $limit
     * @return array<Document>
     *
     * @throws \Exception
     */
    public function getLogs(?int $offset = null, ?int $limit = 25): array
    {
        $queries = [];

        $queries[] = Query::orderDesc('');

        if (! \is_null($offset)) {
            $queries[] = Query::offset($offset);
        }
        if (! \is_null($limit)) {
            $queries[] = Query::limit($limit);
        }

        $response = $this->tablesDB->listRows($this->databaseId, self::TABLE_ID, $queries);

        return \array_map(fn ($document) => new Document($document), $response['documents']);
    }

    /**
     * Delete logs older than $timestamp seconds
     *
     * @param  int  $timestamp
     * @return bool
     *
     * @throws \Exception
     */
    public function cleanup(int $timestamp): bool
    {
        $timestamp = $this->toDateTime($timestamp);

        do {
            $response = $this->tablesDB->deleteRows($this->databaseId, self::TABLE_ID, [
                Query::lessThan('time', $timestamp),
            ]);
        } while ($response['total'] > 0);

        return true;
    }

    protected function toDateTime(int $timestamp): string
    {
        return (new \DateTime())->setTimestamp($timestamp)->format('Y-m-d H:i:s.v');
    }
}
