<?php

namespace Utopia\Abuse\Adapters;

use PDO;
use Utopia\Abuse\Adapter;
use Utopia\Database\Database;
use Utopia\Database\Document;
use Utopia\Database\Query;
use Utopia\Database\Validator\Authorization;
use Utopia\Exception;

class TimeLimit implements Adapter
{
    const COLLECTION = "abuse";

    /**
     * @var Database
     */
    protected $db;

    /**
     * @var string
     */
    protected $key = '';

    /**
     * @var int
     */
    protected $time = 0;

    /**
     * @var int
     */
    protected $limit = 0;

    /**
     * @var int|null
     */
    protected $count = null;

    /**
     * @var array
     */
    protected $params = array();

    /**
     * @param string $key
     * @param int $time
     * @param int $limit
     * @param callable $connection
     */
    public function __construct(string $key, int $limit, int $time, Database $db)
    {
        $this->key = $key;
        $this->time = (int) \date('U', (int) (\floor(\time() / $time)) * $time);
        $this->limit = $limit;
        $this->db = $db;
    }

    public function setup() : void
    {
        if (!$this->db->exists()) {
            throw new Exception("You need to create database before running timelimit setup");
        }
        $this->db->createCollection(TimeLimit::COLLECTION);
        $this->db->createAttribute(TimeLimit::COLLECTION, 'key', Database::VAR_STRING, Database::LENGTH_KEY, true);
        $this->db->createAttribute(TimeLimit::COLLECTION, 'time', Database::VAR_INTEGER, Database::LENGTH_KEY, true);
        $this->db->createAttribute(TimeLimit::COLLECTION, '_count', Database::VAR_INTEGER, 11, true);
        $this->db->createIndex(TimeLimit::COLLECTION, 'unique1', Database::INDEX_UNIQUE, ['key', 'time']);
        $this->db->createIndex(TimeLimit::COLLECTION, 'index1', Database::INDEX_KEY, ['key', 'time']);
        $this->db->createIndex(TimeLimit::COLLECTION, 'index2', Database::INDEX_KEY, ['time']);
    }

    /**
     * Set Param
     *
     * Set custom param for key pattern parsing
     *
     * @param string $key
     * @param string $value
     *
     * @return $this
     */
    public function setParam(string $key, string $value): self
    {
        $this->params[$key] = $value;

        return $this;
    }

    /**
     * Get Params
     *
     * Return array of all key params
     *
     * @return array
     */
    protected function getParams(): array
    {
        return $this->params;
    }

    /**
     * Parse key with all custom attached params
     *
     * @return string
     */
    protected function parseKey(): string
    {
        foreach ($this->getParams() as $key => $value) {
            $this->key = \str_replace($key, $value, $this->key);
        }

        return $this->key;
    }

    /**
     * Check
     *
     * Checks if number of counts is bigger or smaller than current limit
     *
     * @param string $key
     * @param int $time
     * @return int
     * @throws Exception
     */
    protected function count(string $key, int $time): int
    {
        if (0 == $this->limit) { // No limit no point for counting
            return 0;
        }

        if (!\is_null($this->count)) { // Get fetched result
            return $this->count;
        }

        Authorization::disable();
        $result = $this->db->find(TimeLimit::COLLECTION, [
            new Query('key', Query::TYPE_EQUAL, [$key]),
            new Query('time', Query::TYPE_EQUAL, [$time]),
        ], 1);
        Authorization::reset();

        if (\count($result) === 1) {
            $result = $result[0]->getAttribute('_count',0);
        } else {
            $result = 0;
        }

        $this->count = (int) $result;

        return $this->count;
    }

    /**
     * @param string $key
     * @param int $time seconds
     *
     * @throws Exception
     *
     * @return void
     */
    protected function hit(string $key, int $time): void
    {
        if (0 == $this->limit) { // No limit no point for counting
            return;
        }

        Authorization::disable();
        $existing = $this->db->find(TimeLimit::COLLECTION, [
            new Query('key', Query::TYPE_EQUAL, [$key]),
            new Query('time', Query::TYPE_EQUAL, [$time]),
        ], 1);

        $data = [
            '$read' => [],
            '$write' => [],
            'key' => $key,
            'time' => $time,
            '_count' => 1,
            '$collection' => TimeLimit::COLLECTION,
        ];

        if (\count($existing) === 1) {
            //update
            $this->db->updateDocument(TimeLimit::COLLECTION, $existing[0]->getId(), new Document(array_merge($data, [
                '_count' => $existing[0]->getAttribute('_count',0) + 1,
                '$id' => $existing[0]->getId(),
            ])));
        } else {
            //create
            $this->db->createDocument(TimeLimit::COLLECTION, new Document($data));
        }
        Authorization::reset();

        $this->count++;
    }

    /**
     * Get abuse logs
     *
     * Returns logs with an offset and limit
     *
     * @param $offset
     * @param $limit
     *
     * @return array
     */
    public function getLogs(int $offset, int $limit): array
    {
        Authorization::disable();
        $result = $this->db->find(TimeLimit::COLLECTION, [], $limit, $offset);
        Authorization::reset();
        return $result;
    }

    /**
     * Delete logs older than $timestamp seconds
     *
     * @param int $timestamp
     *
     * @return bool
     */
    public function cleanup(int $timestamp): bool
    {
        Authorization::disable();
        do {
            $documents = $this->db->find(TimeLimit::COLLECTION, [
                new Query('time', Query::TYPE_LESSER, [$timestamp]),
            ]);
    
            foreach ($documents as $document) {
                $this->db->deleteDocument(TimeLimit::COLLECTION, $document['$id']);
            }
        } while(\count($documents) > 0);
        Authorization::reset();

        return true;
    }

    /**
     * Check
     *
     * Checks if number of counts is bigger or smaller than current limit. limit 0 is equal to unlimited
     *
     * @return bool
     * @throws Exception
     */
    public function check(): bool
    {
        if (0 == $this->limit) {
            return false;
        }

        $key = $this->parseKey();

        if ($this->limit > $this->count($key, $this->time)) {
            $this->hit($key, $this->time);
            return false;
        }

        return true;
    }

    /**
     * Remaining
     *
     * Returns the number of current remaining counts
     *
     * @throws Exception
     *
     * @return int
     */
    public function remaining(): int
    {
        $left = $this->limit - ($this->count($this->parseKey(), $this->time) + 1); // Add one because we need to say how many left not how many done
        return (0 > $left) ? 0 : $left;
    }

    /**
     * Limit
     *
     * Return the limit integer
     *
     * @return int
     */
    public function limit(): int
    {
        return $this->limit;
    }

    /**
     * Time
     *
     * Return the unix time
     *
     * @return int
     */
    public function time(): int
    {
        return $this->time;
    }
}
