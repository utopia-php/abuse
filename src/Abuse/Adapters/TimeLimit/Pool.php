<?php

use Utopia\Abuse\Adapters\TimeLimit;
use Utopia\Pools\Pool as UtopiaPool;

class Pool extends TimeLimit
{
    protected UtopiaPool $pool;

    /**
     * @param  UtopiaPool<covariant TimeLimit>  $pool The pool to use for connections. Must contain instances of Adapter.
     *
     * @throws \Exception
     */
    public function __construct(UtopiaPool $pool)
    {
        $this->pool = $pool;

        $this->pool->use(function (mixed $resource) {
            if (! ($resource instanceof TimeLimit)) {
                throw new \Exception('Pool must contain instances of '.TimeLimit::class);
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
        return $this->pool->use(function (TimeLimit $adapter) use ($method, $args) {
            return $adapter->{$method}(...$args);
        });
    }

    protected function count(string $key, int $timestamp): int
    {
        return $this->delegate(__FUNCTION__, \func_get_args());
    }

    protected function hit(string $key, int $timestamp): void
    {
        $this->delegate(__FUNCTION__, \func_get_args());
    }

    public function cleanup(int $timestamp): bool
    {
        return $this->delegate(__FUNCTION__, \func_get_args());
    }

    public function getLogs(?int $offset = null, ?int $limit = 25): array
    {
        return $this->delegate(__FUNCTION__, \func_get_args());
    }
}
