<?php

namespace Utopia\Abuse\Adapters\TimeLimit;

use Throwable;
use Utopia\Abuse\Adapters\TimeLimit;
use Utopia\Pools\Pool as UtopiaPool;

class Pool extends TimeLimit
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
        $count = $this->pool->use(function (\Redis $redis) use ($key, $timestamp): int {
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

        $this->pool->use(function (\Redis $redis) use ($key, $ttl): void {
            $redis->multi();
            try {
                $redis->incr($key);
                $redis->expire($key, $ttl);
                $redis->exec();
            } catch (Throwable $th) {
                $this->discard($redis);
                throw $th;
            }
        });

        $this->count = ($this->count ?? 0) + 1;
    }

    protected function set(string $key, int $timestamp, int $value): void
    {
        $ttl = $this->ttl;
        $key = Redis::NAMESPACE . '__' . $key . '__' . $timestamp;

        $this->pool->use(function (\Redis $redis) use ($key, $ttl, $value): void {
            $redis->multi();
            try {
                $redis->set($key, (string) $value);
                $redis->expire($key, $ttl);
                $redis->exec();
            } catch (Throwable $th) {
                $this->discard($redis);
                throw $th;
            }
        });

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
        /** @var array<string, mixed> $result */
        $result = $this->pool->use(function (\Redis $redis) use ($limit) {
            $cursor = null;
            $keys = $redis->scan($cursor, Redis::NAMESPACE . '__*', $limit);
            if (!$keys) {
                return [];
            }

            $logs = [];
            foreach ($keys as $key) {
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

    private function discard(\Redis $redis): void
    {
        try {
            $redis->discard();
        } catch (Throwable) {
        }
    }
}
