<?php

namespace Utopia\Abuse\Adapters\TimeLimit;

use Appwrite\AppwriteException;
use Appwrite\Client;
use Appwrite\Enums\IndexType;
use Appwrite\ID;
use Appwrite\Query;
use Appwrite\Services\TablesDB;
use Utopia\Abuse\Adapters\TimeLimit;
use Utopia\Database\Document;
use Utopia\Exception;

class AppwriteTablesDB extends TimeLimit
{
    public const DATABASE_NAME = 'Utopia';
    // Datbaase ID configurable in constructor
    public const TABLE_NAME = 'abuse';
    public const TABLE_ID = 'Abuse';
    public const TABLE_LOCK = 'lock'; // Lock table created to allow performant check of setup

    protected TablesDB $tablesDB;
    protected string $databaseId;
    protected ?int $count = null;

    public function __construct(string $key, int $limit, int $seconds, Client $client, string $databaseId)
    {
        $this->key = $key;
        $now = \time();
        $this->timestamp = (int)($now - ($now % $seconds));
        $this->limit = $limit;
        $this->tablesDB = new TablesDB($client);
        $this->databaseId = $databaseId;
    }

    /**
     * @throws Exception|\Exception
     */
    public function setup(): void
    {
        try {
            $this->tablesDB->getTable($this->databaseId, self::TABLE_LOCK);
            // Schema indication table exists, we can safely assume database is setup
            return;
        } catch (\Throwable $err) {
            // Error occured, we do in-depth setup
        }

        try {
            $this->tablesDB->create($this->databaseId, self::DATABASE_NAME);
        } catch (AppwriteException $err) {
            // Silence error if database is already present
            if ($err->getType() !== 'database_already_exists') {
                throw $err;
            }
        }

        try {
            $this->tablesDB->createTable($this->databaseId, self::TABLE_ID, self::TABLE_NAME);
        } catch (AppwriteException $err) {
            // Silence error if table is already present
            if ($err->getType() !== 'table_already_exists') {
                throw $err;
            }
        }

        try {
            $this->tablesDB->createStringColumn($this->databaseId, self::TABLE_ID, 'key', 255, true);
        } catch (AppwriteException $err) {
            // Silence error if column is already present
            if ($err->getType() !== 'column_already_exists') {
                throw $err;
            }
        }

        try {
            $this->tablesDB->createDatetimeColumn($this->databaseId, self::TABLE_ID, 'time', true);
        } catch (AppwriteException $err) {
            // Silence error if column is already present
            if ($err->getType() !== 'column_already_exists') {
                throw $err;
            }
        }

        try {
            $this->tablesDB->createIntegerColumn($this->databaseId, self::TABLE_ID, 'count', true, 0, PHP_INT_MAX);
        } catch (AppwriteException $err) {
            // Silence error if column is already present
            if ($err->getType() !== 'column_already_exists') {
                throw $err;
            }
        }

        // Await till all attributes are ready
        $ready = false;
        $attempts = 0;
        while ($attempts < 15) {
            $attempts++;

            $response = $this->tablesDB->listColumns($this->databaseId, self::TABLE_ID, [
                Query::notEqual('status', 'available'),
                Query::limit(1)
            ]);

            $columns = $response['columns'];

            // Temporary fix due to bug in Appwrite listColumns queries
            $columns = \array_filter($columns, fn ($column) => $column['status'] !== 'available');

            if (\count($columns) === 0) {
                $ready = true;
                break;
            }

            \sleep(1);
        }

        if (!$ready) {
            throw new \Exception('Failed to setup tables.');
        }

        try {
            $this->tablesDB->createIndex($this->databaseId, self::TABLE_ID, 'unique1', IndexType::UNIQUE(), ['key', 'time']);
        } catch (AppwriteException $err) {
            // Silence error if index is already present
            if ($err->getType() !== 'index_already_exists') {
                throw $err;
            }
        }

        try {
            $this->tablesDB->createIndex($this->databaseId, self::TABLE_ID, 'index2', IndexType::KEY(), ['time']);
        } catch (AppwriteException $err) {
            // Silence error if index is already present
            if ($err->getType() !== 'index_already_exists') {
                throw $err;
            }
        }

        // Await till all indexes are ready
        $ready = false;
        $attempts = 0;
        while ($attempts < 15) {
            $attempts++;

            $response = $this->tablesDB->listIndexes($this->databaseId, self::TABLE_ID, [
                Query::notEqual('status', 'available'),
                Query::limit(1)
            ]);

            $indexes = $response['indexes'];

            // Temporary fix due to bug in Appwrite listColumns queries
            $indexes = \array_filter($indexes, fn ($index) => $index['status'] !== 'available');

            if (\count($indexes) === 0) {
                $ready = true;
                break;
            }

            \sleep(1);
        }

        if (!$ready) {
            throw new \Exception('Failed to setup tables.');
        }

        // Optimize future setup checks
        try {
            $this->tablesDB->createTable($this->databaseId, self::TABLE_LOCK, name: self::TABLE_LOCK);
        } catch (AppwriteException $err) {
            // Silence error if table is already present
            if ($err->getType() !== 'table_already_exists') {
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
