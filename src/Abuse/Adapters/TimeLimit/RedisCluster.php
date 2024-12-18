<?php

namespace Utopia\Abuse\Adapters\TimeLimit;

use Utopia\Abuse\Adapters\TimeLimit;

class RedisCluster extends TimeLimit
{
    public const NAMESPACE = 'abuse';

    /**
     * @var \RedisCluster
     */
    protected \RedisCluster $redis;

    public function __construct(string $key, int $limit, int $seconds, \RedisCluster $redis)
    {
        $this->redis = $redis;
        $this->key = $key;
        $time = (int) \date('U', (int) (\floor(\time() / $seconds)) * $seconds);
        $this->time = strval($time);
        $this->limit = $limit;
    }

    /**
     * Get count for a key at specific datetime
     *
     * @param string $key
     * @param string $datetime
     * @return integer
     */
    protected function count(string $key, string $datetime): int
    {
        if (0 == $this->limit) { // No limit no point for counting
            return 0;
        }

        if (! \is_null($this->count)) { // Get fetched result
            return $this->count;
        }

        /** @var string|false $count */
        $count = $this->redis->get(self::NAMESPACE . '__'. $key .'__'. $datetime);
        if ($count === false) {
            $this->count = 0;
        } else {
            $this->count = intval($count);
        }

        return $this->count;
    }

    /**
     * Record a hit for a key at specific datetime
     *
     * @param string $key
     * @param string $datetime
     * @return void
     */
    protected function hit(string $key, string $datetime): void
    {
        if (0 == $this->limit) { // No limit no point for counting
            return;
        }

        /** @var string|false $count */
        $count = $this->redis->get(self::NAMESPACE . '__'. $key .'__'. $datetime);
        if ($count === false) {
            $this->count = 0;
        } else {
            $this->count = intval($count);
        }

        $this->redis->incr(self::NAMESPACE . '__'. $key .'__'. $datetime);
        $this->count++;
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
    public function getLogs(?int $offset = 0, ?int $limit = 25): array
    {
        // TODO limit potential is SCAN but needs cursor no offset
        $keys = $this->scan(self::NAMESPACE . '__*', $offset, $limit);
        if ($keys === false) {
            return [];
        }

        $logs = [];
        foreach ($keys as $key) {
            $logs[$key] = $this->redis->get($key);
        }
        return $logs;
    }

    /**
     * Delete all logs older than $datetime
     *
     * @param string $datetime
     * @return bool
     */
    public function cleanup(string $datetime): bool
    {
        $keys = $this->scan(self::NAMESPACE . '__*__*');
        $keys = $this->filterKeys($keys ? $keys : [], (int) $datetime);
        /** @phpstan-ignore-next-line */
        $this->redis->del($keys);
        return true;
    }

    /**
     * Filter keys
     *
     * @param array<string> $keys
     * @param integer $timestamp
     * @return array<string>
     */
    protected function filterKeys(array $keys, int $timestamp): array
    {
        $filteredKeys = [];
        foreach ($keys as $key) {
            $parts = explode('__', $key);
            $keyTimestamp = (int)end($parts); // Assuming the last part is always the timestamp
            if ($keyTimestamp < $timestamp) {
                $filteredKeys[] = $key;
            }
        }
        return $filteredKeys;
    }

    /**
     * Scan keys across all masters in the Redis cluster
     *
     * @param string $pattern Pattern to match keys
     * @param int|null $cursor Reference to the cursor for scanning
     * @param int|null $count Number of keys to return per iteration
     * @return array<string>|false
     */
    protected function scan(string $pattern, ?int &$cursor = null, ?int $count = 1000): array|false
    {
        $matches = [];
        foreach ($this->redis->_masters() as $master) {
            $cursor = null;
            do {
                /** @phpstan-ignore-next-line */
                $keys = $this->redis->scan($cursor, $master, $pattern, $count);
                if ($keys !== false) {
                    $matches = array_merge($matches, $keys);
                }
            } while ($cursor > 0);
        }

        return empty($matches) ? false : $matches;
    }
}
