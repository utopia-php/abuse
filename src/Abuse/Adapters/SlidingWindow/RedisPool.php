<?php

namespace Utopia\Abuse\Adapters\SlidingWindow;

use Utopia\Pools\Pool as UtopiaPool;

class RedisPool extends RedisBase
{
    /**
     * @param  string  $key         Abuse key pattern, e.g. "ip:{ip}"; params are substituted via setParam()
     * @param  int  $limit          Max allowed hits per window; 0 means unlimited
     * @param  int  $windowSize     Length of the rate-limit window in seconds
     * @param  int  $ttl            Lifetime of a bucket key in seconds; must be >= $windowSize so the
     *                              previous window's bucket survives long enough to be weighted
     * @param  UtopiaPool<\Redis>|UtopiaPool<\RedisCluster>  $pool  Pool yielding a Redis or RedisCluster connection
     */
    public function __construct(
        protected string $key,
        protected int $limit,
        int $windowSize,
        int $ttl,
        protected UtopiaPool $pool
    ) {
        $this->initWindow($windowSize, $ttl);
    }

    /**
     * @param  list<string>  $keys
     * @param  list<int|float>  $argv
     * @return array{0:int,1:int,2:int}
     */
    protected function evaluateLimit(array $keys, array $argv): array
    {
        /** @var array{0:int,1:int,2:int} $result */
        $result = $this->pool->use(function (\Redis|\RedisCluster $redis) use ($keys, $argv): array {
            /** @var array{0:int,1:int,2:int} $result */
            $result = $redis->eval(self::LIMIT_CHECK_SCRIPT, [...$keys, ...$argv], \count($keys));

            return $result;
        });

        return $result;
    }

    /**
     * @param  string  $key
     * @return int
     */
    protected function bucketCount(string $key): int
    {
        /** @var int $value */
        $value = $this->pool->use(function (\Redis|\RedisCluster $redis) use ($key): int {
            $raw = $redis->get($key);

            return \is_numeric($raw) ? (int) $raw : 0;
        });

        return $value;
    }

    /**
     * @param  string  ...$keys
     * @return void
     */
    protected function deleteBuckets(string ...$keys): void
    {
        $this->pool->use(function (\Redis|\RedisCluster $redis) use ($keys): void {
            $redis->del(...$keys);
        });
    }

    /**
     * Get abuse logs
     *
     * Return logs with an offset and limit
     *
     * @param  int|null  $offset
     * @param  int|null  $limit
     * @return array<string, mixed>
     */
    public function getLogs(?int $offset = null, ?int $limit = 25): array
    {
        $offset = $offset ?? 0;
        $limit = $limit ?? 25;

        /** @var array<string, mixed> $result */
        $result = $this->pool->use(function (\Redis|\RedisCluster $redis) use ($offset, $limit): array {
            if ($redis instanceof \RedisCluster) {
                return $this->getRedisClusterLogs($redis, $offset, $limit);
            }

            $cursor = null;
            $matches = [];
            $pattern = self::NAMESPACE . '__*';

            do {
                $keys = $redis->scan($cursor, $pattern, 100);
                if ($keys !== false) {
                    \array_push($matches, ...$keys);
                }
            } while ($cursor > 0);

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
     * @param  \RedisCluster  $redis
     * @param  int  $offset
     * @param  int  $limit
     * @return array<string, mixed>
     */
    private function getRedisClusterLogs(\RedisCluster $redis, int $offset, int $limit): array
    {
        $matches = [];
        $pattern = self::NAMESPACE . '__*';

        foreach ($redis->_masters() as $master) {
            $cursor = null;
            do {
                /** @phpstan-ignore-next-line */
                $keys = $redis->scan($cursor, $master, $pattern, 100);
                if ($keys !== false) {
                    \array_push($matches, ...$keys);
                }
            } while ($cursor > 0);
        }

        \sort($matches);
        $matches = \array_slice($matches, $offset, $limit);

        if (empty($matches)) {
            return [];
        }

        $values = $redis->mget($matches);
        if (!\is_array($values)) {
            return [];
        }

        $logs = \array_combine($matches, $values);
        if (!\is_array($logs)) {
            return [];
        }

        return $logs;
    }
}
