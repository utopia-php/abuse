<?php

namespace Utopia\Abuse\Adapters\TimeLimit;

use RuntimeException;
use Throwable;
use Utopia\Abuse\Adapters\TimeLimit;
use Utopia\Pools\Connection;
use Utopia\Pools\Pool as UtopiaPool;

class RedisPool extends TimeLimit
{
    /**
     * @var int
     */
    protected int $ttl;

    /**
     * @param UtopiaPool<\Redis> $pool
     */
    public function __construct(
        string $key,
        int $limit,
        int $seconds,
        protected UtopiaPool $pool
    ) {
        $this->key = $key;
        $this->ttl = $seconds;
        $now = \time();
        $this->timestamp = (int) ($now - ($now % $seconds));
        $this->limit = $limit;
    }

    protected function count(string $key, int $timestamp): int
    {
        if (0 == $this->limit) {
            return 0;
        }

        if (!\is_null($this->count)) {
            return $this->count;
        }

        /** @var int $count */
        $count = $this->useRedis(function (\Redis $redis) use ($key, $timestamp): int {
            $count = $redis->get(Redis::NAMESPACE . '__' . $key . '__' . $timestamp);

            return \is_numeric($count) ? (int) $count : 0;
        });

        $this->count = $count;

        return $this->count;
    }

    protected function hit(string $key, int $timestamp): void
    {
        if (0 == $this->limit) {
            return;
        }

        $ttl = $this->ttl;
        $key = Redis::NAMESPACE . '__' . $key . '__' . $timestamp;

        $this->useRedis(function (\Redis $redis) use ($key, $ttl): void {
            $redis->multi();
            $redis->incr($key);
            $redis->expire($key, $ttl);
            $result = $redis->exec();

            if (!\is_array($result) || \in_array(false, $result, true)) {
                throw new RuntimeException('Redis transaction failed.');
            }
        }, true);

        $this->count = ($this->count ?? 0) + 1;
    }

    protected function set(string $key, int $timestamp, int $value): void
    {
        $ttl = $this->ttl;
        $key = Redis::NAMESPACE . '__' . $key . '__' . $timestamp;

        $this->useRedis(function (\Redis $redis) use ($key, $ttl, $value): void {
            $redis->multi();
            $redis->set($key, (string) $value);
            $redis->expire($key, $ttl);
            $result = $redis->exec();

            if (!\is_array($result) || \in_array(false, $result, true)) {
                throw new RuntimeException('Redis transaction failed.');
            }
        }, true);

        $this->count = $value;
    }

    /**
     * Get abuse logs
     *
     * Return logs with an offset and limit
     *
     * @param int|null $offset
     * @param int|null $limit
     * @return array<string, mixed>
     */
    public function getLogs(?int $offset = null, ?int $limit = 25): array
    {
        $offset = $offset ?? 0;
        $limit = $limit ?? 25;

        /** @var array<string, mixed> $result */
        $result = $this->useRedis(function (\Redis $redis) use ($offset, $limit) {
            $cursor = null;
            $matches = [];
            $pattern = Redis::NAMESPACE . '__*';

            do {
                $keys = $redis->scan($cursor, $pattern, 100);
                if ($keys !== false) {
                    \array_push($matches, ...$keys);
                }
            } while ($cursor > 0 && \count($matches) < $offset + $limit);

            \sort($matches);
            $matches = \array_slice($matches, $offset, $limit);

            if (empty($matches)) {
                return [];
            }

            $logs = [];
            foreach ($matches as $key) {
                $logs[$key] = $redis->get($key);
            }

            return $logs;
        });

        return $result;
    }

    /**
     * Delete all logs older than $timestamp
     *
     * @param int $timestamp
     * @return bool
     */
    public function cleanup(int $timestamp): bool
    {
        return true;
    }

    /**
     * @template T
     * @param callable(\Redis): T $callback
     * @return T
     * @throws Throwable
     */
    private function useRedis(callable $callback, bool $discardTransaction = false): mixed
    {
        /** @var Connection<\Redis> $connection */
        $connection = $this->pool->pop();
        $redis = $connection->getResource();

        try {
            $result = $callback($redis);
        } catch (Throwable $th) {
            if ($discardTransaction) {
                $this->discard($redis);
            }
            try {
                $connection->destroy();
            } catch (Throwable) {
            }
            throw $th;
        }

        $connection->reclaim();

        return $result;
    }

    private function discard(\Redis $redis): void
    {
        try {
            $redis->discard();
        } catch (Throwable) {
        }
    }
}
