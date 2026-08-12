<?php

namespace Utopia\Abuse\Adapters\SlidingWindow;

use Utopia\Abuse\Adapters\SlidingWindow;

/**
 * Shared implementation for the Redis-family sliding-window adapters
 * (Redis, RedisCluster, RedisPool). Owns the atomic Lua script, the window
 * math and the check/count/reset flow. Subclasses only implement how they
 * talk to storage via the eval()/get()/delete() seams, plus their own getLogs().
 */
abstract class RedisBase extends SlidingWindow
{
    public const NAMESPACE = 'abuse';

    /**
     * Atomic sliding-window check-and-increment.
     *
     * KEYS[1] current bucket, KEYS[2] previous bucket.
     * ARGV[1] max_requests, ARGV[2] elapsed fraction of current window [0,1), ARGV[3] ttl seconds.
     *
     * Returns { allowed (0|1), remaining, estimate } where estimate is the
     * weighted sliding-window count (current bucket + weighted previous bucket).
     */
    protected const string LIMIT_CHECK_SCRIPT = <<<'LUA'
        local current_key = KEYS[1]
        local previous_key = KEYS[2]
        local max_requests = tonumber(ARGV[1])
        local elapsed = tonumber(ARGV[2])
        local ttl = tonumber(ARGV[3])

        local prev_count = tonumber(redis.call('GET', previous_key) or '0') or 0
        local current_count = tonumber(redis.call('GET', current_key) or '0') or 0

        local weighted_prev = prev_count * (1 - elapsed)
        local estimated = weighted_prev + current_count

        if estimated >= max_requests then
          return { 0, 0, math.floor(estimated) }
        end

        local new_count = redis.call('INCR', current_key)
        redis.call('EXPIRE', current_key, ttl)

        local new_estimate = weighted_prev + new_count
        local remaining = math.max(0, math.floor(max_requests - new_estimate))
        return { 1, remaining, math.floor(new_estimate) }
        LUA;

    /**
     * @var int
     */
    protected int $windowSize;

    /**
     * @var int
     */
    protected int $ttl;

    /**
     * @var float
     */
    protected float $elapsed;

    /**
     * Run a Lua script against the storage backend.
     *
     * @param  string  $script
     * @param  list<string>  $keys
     * @param  list<int|float>  $argv
     * @return mixed  the raw script result
     */
    abstract protected function eval(string $script, array $keys, array $argv): mixed;

    /**
     * Get the raw value stored at $key (null/false when missing).
     *
     * @param  string  $key
     * @return mixed
     */
    abstract protected function get(string $key): mixed;

    /**
     * Delete the given keys.
     *
     * @param  string  ...$keys
     * @return void
     */
    abstract protected function delete(string ...$keys): void;

    /**
     * Initialise the window boundaries from the configured window size and ttl.
     * Both `timestamp` (window start) and `elapsed` are derived from a single
     * `now` so they stay consistent with the bucket being written.
     *
     * The window is captured once, at construction. An adapter instance is meant
     * to be short-lived (created per request); reusing one across a window
     * boundary keeps operating on the construction-time window. This matches the
     * TimeLimit adapters' behaviour - construct a fresh adapter per check.
     *
     * @param  int  $windowSize
     * @param  int  $ttl
     * @return void
     */
    protected function initWindow(int $windowSize, int $ttl): void
    {
        if ($windowSize <= 0) {
            throw new \InvalidArgumentException('windowSize must be greater than 0');
        }

        // The previous bucket keeps contributing (weighted) throughout the whole
        // current window, and its ttl is set from its last write - which in the
        // worst case is at the very start of its own window. It therefore needs to
        // survive up to two full windows, so ttl must be >= 2 * windowSize.
        if ($ttl < $windowSize * 2) {
            throw new \InvalidArgumentException('ttl must be at least twice the windowSize so the previous window bucket outlives the current window');
        }

        $now = \time();
        $this->windowSize = $windowSize;
        $this->ttl = $ttl;
        $this->timestamp = (int)($now - ($now % $windowSize)); // start of the current window
        $this->elapsed = ($now - $this->timestamp) / $windowSize; // always in [0,1)
    }

    /**
     * Build a bucket key. The hash tag around $key forces the current and previous
     * window buckets into the same cluster slot, so the multi-key Lua script and
     * reset() do not raise CROSSSLOT on a Redis Cluster (harmless on single Redis).
     *
     * @param  string  $key
     * @param  int  $timestamp
     * @return string
     */
    protected function bucketKey(string $key, int $timestamp): string
    {
        return self::NAMESPACE . '__{' . $key . '}__' . $timestamp;
    }

    /**
     * Check
     *
     * @return bool
     *
     * @throws \Throwable
     */
    public function check(): bool
    {
        if ($this->limit === 0) {
            return false;
        }

        $key = $this->parseKey();

        /** @var array{0:int,1:int,2:int} $result */
        $result = $this->eval(
            self::LIMIT_CHECK_SCRIPT,
            [
                $this->bucketKey($key, $this->timestamp),                       // KEYS[1] current bucket
                $this->bucketKey($key, $this->timestamp - $this->windowSize),   // KEYS[2] previous bucket
            ],
            [
                $this->limit,    // ARGV[1] max_requests
                $this->elapsed,  // ARGV[2] elapsed fraction
                $this->ttl,      // ARGV[3] ttl seconds
            ],
        );

        // $estimate is the weighted sliding-window count, so a following
        // remaining() call stays consistent with what check() decided on.
        [$allowed, , $estimate] = $result;
        $this->count = $estimate;

        return $allowed === 0;
    }

    /**
     * Count
     *
     * Read-only weighted estimate of hits in the current sliding window
     * (current bucket + weighted previous bucket). Used by remaining().
     *
     * @param  string  $key
     * @param  int  $timestamp
     * @return int
     */
    protected function count(string $key, int $timestamp): int
    {
        if (0 == $this->limit) {
            return 0;
        }

        if (! \is_null($this->count)) {
            return $this->count;
        }

        $currentRaw = $this->get($this->bucketKey($key, $timestamp));
        $previousRaw = $this->get($this->bucketKey($key, $timestamp - $this->windowSize));

        $current = \is_numeric($currentRaw) ? (int) $currentRaw : 0;
        $previous = \is_numeric($previousRaw) ? (int) $previousRaw : 0;

        $this->count = (int) \floor($current + $previous * (1 - $this->elapsed));

        return $this->count;
    }

    /**
     * Reset
     *
     * Clear both the current and previous window buckets so the limit starts fresh.
     *
     * @return void
     */
    public function reset(): void
    {
        $key = $this->parseKey();

        $this->delete(
            $this->bucketKey($key, $this->timestamp),
            $this->bucketKey($key, $this->timestamp - $this->windowSize),
        );

        $this->count = 0;
    }

    /**
     * No need for manual cleanup - Redis TTL handles this automatically
     *
     * @param  int  $timestamp
     * @return bool
     */
    public function cleanup(int $timestamp): bool
    {
        return true;
    }
}
