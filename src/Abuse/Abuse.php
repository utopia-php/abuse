<?php

namespace Utopia\Abuse;

class Abuse
{
    /**
     * @var Adapter
     */
    protected $adapter;

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
    public function check()
    {
        return $this->adapter->check();
    }

    /**
     * Delete all logs older than $seconds seconds
     *
     * @param int $seconds
     * 
     * @return bool
     */
    public function deleteLogsOlderThan(int $seconds): bool
    {
        return $this->adapter->deleteLogsOlderThan($seconds);
    }
}