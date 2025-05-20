<?php

namespace Utopia\Abuse\Adapters\TimeLimit;

use Utopia\Abuse\Adapters\TimeLimit;
use Utopia\Pools\Pool as UtopiaPool;
use Utopia\Abuse\Adapters\TimeLimit\Redis as RedisAdapter;
use Redis;

class Pool extends TimeLimit
{
    /**
    * @var UtopiaPool<covariant Redis>
    */
    protected UtopiaPool $pool;

    protected string $key;
    protected int $limit;
    protected int $seconds;

    /**
     * @param string $key
     * @param int $limit
     * @param int $seconds
     * @param  UtopiaPool<covariant Redis>  $pool The pool to use for connections. Must contain instances of TimeLimit.
     *
     * @throws \Exception
     */
    public function __construct(string $key, int $limit, int $seconds, UtopiaPool $pool)
    {
        $this->pool = $pool;
        $this->key = $key;
        $this->limit = $limit;
        $this->seconds = $seconds;

        $this->pool->use(function (mixed $resource) {
            if (! ($resource instanceof Redis)) {
                throw new \Exception('Pool must contain instances of '.Redis::class);
            }
        });
    }

    /**
     * Forward method calls to the internal adapter instance via the pool.
     *
     * Required because __call() can't be used to implement abstract methods.
     *
     * @param  string  $method
     * @param  array<mixed>  $args
     * @return mixed
     */
    public function delegate(string $method, array $args): mixed
    {
        return $this->pool->use(function (Redis $redis) use ($method, $args) {
            $adapter = new RedisAdapter($this->key, $this->limit, $this->seconds, $redis);
            return $adapter->{$method}(...$args);
        });
    }

    protected function count(string $key, int $timestamp): int
    {
        /**
         * @var int $result
         */
        $result = $this->delegate(__FUNCTION__, \func_get_args());
        return $result;
    }

    protected function hit(string $key, int $timestamp): void
    {
        $this->delegate(__FUNCTION__, \func_get_args());
    }

    public function cleanup(int $timestamp): bool
    {
        /**
         * @var bool $result
         */
        $result = $this->delegate(__FUNCTION__, \func_get_args());
        return $result;
    }

    public function getLogs(?int $offset = null, ?int $limit = 25): array
    {
        /**
         * @var array<string, mixed> $result
         */
        $result = $this->delegate(__FUNCTION__, \func_get_args());
        return $result;
    }
}
