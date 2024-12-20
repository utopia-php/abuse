<?php

namespace Utopia\Abuse\Adapters\TimeLimit;

use Utopia\Abuse\Adapters\TimeLimit;

class Redis extends TimeLimit
{
    public const NAMESPACE = 'abuse';

    /**
     * @var \Redis
     */
    protected \Redis $redis;

    public function __construct(string $key, int $limit, int $seconds, \Redis $redis)
    {
        $this->redis = $redis;
        $this->key = $key;
        $now = \time();
        $this->timestamp = (int)($now - ($now % $seconds));
        $this->limit = $limit;
    }

    /**
     * Undocumented function
     *
     * @param string $key
     * @param int $timestamp
     * @return integer
     */
    protected function count(string $key, int $timestamp): int
    {
        if (0 == $this->limit) { // No limit no point for counting
            return 0;
        }

        if (! \is_null($this->count)) { // Get fetched result
            return $this->count;
        }

        /** @var string $count */
        $count = $this->redis->get(self::NAMESPACE . '__'. $key .'__'. $timestamp);
        if (!$count) {
            $this->count = 0;
        } else {
            $this->count = intval($count);
        }

        return $this->count;
    }

    /**
     * @param  string  $key
     * @param  int  $timestamp
     * @return void
     *
     */
    protected function hit(string $key, int $timestamp): void
    {
        if (0 == $this->limit) { // No limit no point for counting
            return;
        }

        /** @var string $count */
        $count = $this->redis->get(self::NAMESPACE . '__'. $key .'__'. $timestamp);
        if (!$count) {
            $this->count = 0;
        } else {
            $this->count = intval($count);
        }

        $this->redis->incr(self::NAMESPACE . '__'. $key .'__'. $timestamp);
        $this->count++;
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
        // TODO limit potential is SCAN but needs cursor no offset
        $cursor = null;
        $keys = $this->redis->scan($cursor, self::NAMESPACE . '__*', $limit);
        if (!$keys) {
            return [];
        }

        $logs = [];
        foreach ($keys as $key) {
            $logs[$key] = $this->redis->get($key);
        }
        return $logs;
    }

    /**
     * Delete all logs older than $timestamp
     *
     * @param  int  $timestamp
     * @return bool
     */
    public function cleanup(int $timestamp): bool
    {
        $iterator = null;
        while ($iterator !== 0) {
            $keys = $this->redis->scan($iterator, self::NAMESPACE . '__*__*', 1000);
            $keys = $this->filterKeys($keys ? $keys : [], $timestamp);
            $this->redis->del($keys);
        }
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
}
