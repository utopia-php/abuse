<?php

namespace Utopia\Abuse\Adapters;

use Throwable;
use Utopia\Abuse\Adapter;

abstract class TimeLimit extends Adapter
{
    /**
     * @var int
     */
    protected int $limit = 0;

    /**
     * @var int|null
     */
    protected ?int $count = null;

    /**
     * @var string
     */
    protected string $time;

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
    abstract protected function count(string $key, string $datetime): int;

    abstract protected function hit(string $key, string $datetime): void;

    /**
     * Check
     *
     * Checks if number of counts is bigger or smaller than current limit. limit 0 is equal to unlimited
     *
     * @return bool
     *
     * @throws \Exception|Throwable
     */
    public function check(): bool
    {
        if (0 == $this->limit) {
            return false;
        }

        $key = $this->parseKey();

        if ($this->limit > $this->count($key, $this->time)) {
            $this->hit($key, $this->time);

            return false;
        }

        return true;
    }

    /**
     * Remaining
     *
     * Returns the number of current remaining counts
     *
     * @return int
     *
     * @throws \Exception
     */
    public function remaining(): int
    {
        $left = $this->limit - ($this->count($this->parseKey(), $this->time) + 1); // Add one because we need to say how many left not how many done

        return (0 > $left) ? 0 : $left;
    }

    /**
     * Limit
     *
     * Return the limit integer
     *
     * @return int
     */
    public function limit(): int
    {
        return $this->limit;
    }

    /**
     * Time
     *
     * Return the Datetime
     *
     * @return string
     */
    public function time(): string
    {
        return $this->time;
    }
}
