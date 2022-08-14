<?php

namespace Utopia\Abuse;

class Abuse
{
    /**
     * @var Adapter
     */
    protected Adapter $adapter;

    /**
     * @param Adapter $adapter
     */
    public function __construct(Adapter $adapter)
    {
        $this->adapter  = $adapter;
    }

    /**
     * Check
     *
     * Checks if request is considered abuse or not
     *
     * @return bool
     */
    public function check(): bool
    {
        return $this->adapter->check();
    }

    /**
     * Get abuse logs
     *
     * Return logs with an offset and limit
     *
     * @param int $offset
     * @param int $limit
     *
     * @return array
     */
    public function getLogs(int $offset, int $limit): array
    {
        return $this->adapter->getLogs($offset, $limit);
    }

    /**
     * Delete all logs older than $datetime
     *
     * @param string $datetime
     *
     * @return bool
     */
    public function cleanup(string $datetime): bool
    {
        return $this->adapter->cleanup($datetime);
    }
}