<?php

namespace Utopia\Abuse\Adapters;

use Utopia\Database\Database as UtopiaDB;
use Utopia\Database\DateTime;
use Utopia\Database\Document;
use Utopia\Database\Exception\Authorization as AuthorizationException;
use Utopia\Database\Exception\Duplicate;
use Utopia\Database\Exception\Structure;
use Utopia\Database\Query;
use Utopia\Database\Validator\Authorization;
use Utopia\Exception;

class Database extends TimeLimit
{
    public const COLLECTION = 'abuse';

    /**
     * @var UtopiaDB
     */
    protected UtopiaDB $db;

    /**
     * @var int|null
     */
    protected ?int $count = null;

    /**
     * @param  string  $key
     * @param  int  $seconds
     * @param  int  $limit
     * @param  UtopiaDB  $db
     */
    public function __construct(string $key, int $limit, int $seconds, UtopiaDB $db)
    {
        $this->key = $key;
        $time = (int) \date('U', (int) (\floor(\time() / $seconds)) * $seconds); // todo: any good Idea without time()?
        $this->time = DateTime::format((new \DateTime())->setTimestamp($time));
        $this->limit = $limit;
        $this->db = $db;
    }

    /**
     * @throws Duplicate
     * @throws Exception|\Exception
     */
    public function setup(): void
    {
        if (! $this->db->exists($this->db->getDatabase())) {
            throw new Exception('You need to create database before running timelimit setup');
        }

        $attributes = [
            new Document([
                '$id' => 'key',
                'type' => UtopiaDB::VAR_STRING,
                'size' => UtopiaDB::LENGTH_KEY,
                'required' => true,
                'signed' => true,
                'array' => false,
                'filters' => [],
            ]),
            new Document([
                '$id' => 'time',
                'type' => UtopiaDB::VAR_DATETIME,
                'size' => 0,
                'required' => true,
                'signed' => false,
                'array' => false,
                'filters' => ['datetime'],
            ]),
            new Document([
                '$id' => 'count',
                'type' => UtopiaDB::VAR_INTEGER,
                'size' => 11,
                'required' => true,
                'signed' => false,
                'array' => false,
                'filters' => [],
            ]),
        ];

        $indexes = [
            new Document([
                '$id' => 'unique1',
                'type' => UtopiaDB::INDEX_UNIQUE,
                'attributes' => ['key', 'time'],
                'lengths' => [],
                'orders' => [],
            ]),
            new Document([
                '$id' => 'index2',
                'type' => UtopiaDB::INDEX_KEY,
                'attributes' => ['time'],
                'lengths' => [],
                'orders' => [],
            ]),
        ];

        try {
            $this->db->createCollection(Database::COLLECTION, $attributes, $indexes);
        } catch (Duplicate) {
            // Collection already exists
        }
    }

    /**
     * Check
     *
     * Checks if number of counts is bigger or smaller than current limit
     *
     * @param  string  $key
     * @param  string  $datetime
     * @return int
     *
     * @throws \Exception
     */
    protected function count(string $key, string $datetime): int
    {
        if (0 == $this->limit) { // No limit no point for counting
            return 0;
        }

        if (! \is_null($this->count)) { // Get fetched result
            return $this->count;
        }

        /** @var array<Document> $result */
        $result = Authorization::skip(function () use ($key, $datetime) {
            return $this->db->find(Database::COLLECTION, [
                Query::equal('key', [$key]),
                Query::equal('time', [$datetime]),
            ]);
        });

        $this->count = 0;

        if (\count($result) === 1) { // Unique Index
            $count = $result[0]->getAttribute('count', 0);
            if (\is_numeric($count)) {
                $this->count = intval($count);
            }
        }

        return $this->count;
    }

    /**
     * @param  string  $key
     * @param  string  $datetime
     * @return void
     *
     * @throws AuthorizationException|Structure|\Exception|\Throwable
     */
    protected function hit(string $key, string $datetime): void
    {
        if (0 == $this->limit) { // No limit no point for counting
            return;
        }

        Authorization::skip(function () use ($datetime, $key) {
            $data = $this->db->findOne(Database::COLLECTION, [
                Query::equal('key', [$key]),
                Query::equal('time', [$datetime]),
            ]);

            if ($data === false) {
                $data = [
                    '$permissions' => [],
                    'key' => $key,
                    'time' => $datetime,
                    'count' => 1,
                    '$collection' => Database::COLLECTION,
                ];

                try {
                    $this->db->createDocument(Database::COLLECTION, new Document($data));
                } catch (Duplicate $e) {
                    // Duplicate in case of race condition
                    $data = $this->db->findOne(Database::COLLECTION, [
                        Query::equal('key', [$key]),
                        Query::equal('time', [$datetime]),
                    ]);

                    if ($data !== false && $data instanceof Document) {
                        $count = $data->getAttribute('count', 0);
                        if (\is_numeric($count)) {
                            $this->count = intval($count);
                        }
                        $this->db->increaseDocumentAttribute(Database::COLLECTION, $data->getId(), 'count');
                    } else {
                        throw new \Exception('Document Not Found');
                    }
                }
            } else {
                /** @var Document $data */
                $this->db->increaseDocumentAttribute(Database::COLLECTION, $data->getId(), 'count');
            }
        });

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
        /** @var array<Document> $results */
        $results = Authorization::skip(function () use ($offset, $limit) {
            $queries = [];
            $queries[] = Query::orderDesc('');

            if (! \is_null($offset)) {
                $queries[] = Query::offset($offset);
            }
            if (! \is_null($limit)) {
                $queries[] = Query::limit($limit);
            }

            return $this->db->find(Database::COLLECTION, $queries);
        });

        return $results;
    }

    /**
     * Delete logs older than $timestamp seconds
     *
     * @param  string  $datetime
     * @return bool
     *
     * @throws AuthorizationException|\Exception
     */
    public function cleanup(string $datetime): bool
    {
        Authorization::skip(function () use ($datetime) {
            do {
                $documents = $this->db->find(Database::COLLECTION, [
                    Query::lessThan('time', $datetime),
                ]);

                foreach ($documents as $document) {
                    $this->db->deleteDocument(Database::COLLECTION, $document->getId());
                }
            } while (! empty($documents));
        });

        return true;
    }
}
