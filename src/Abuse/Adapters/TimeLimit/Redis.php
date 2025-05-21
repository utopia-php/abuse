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

    /**
     * @var int
     */
    protected int $ttl;

    public function __construct(string $key, int $limit, int $seconds, \Redis $redis)
    {
        $this->redis = $redis;
        $this->key = $key;
        $this->ttl = $seconds;
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

        /** @var string $count */
        $count = $this->redis->get(self::NAMESPACE . '__'. $key .'__'. $timestamp);
        if (!$count) {
            $count = 0;
        } else {
            $count = intval($count);
        }

        return $count;
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

        $key = self::NAMESPACE . '__' . $key . '__' . $timestamp;
        $this->redis->multi()
            ->incr($key)
            ->expire($key, $this->ttl)
            ->exec();
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
        // No need for manual cleanup - Redis TTL handles this automatically
        return true;
    }
}
