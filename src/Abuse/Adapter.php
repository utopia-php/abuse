<?php

namespace Utopia\Abuse;

interface Adapter
{
    /**
     * Check
     *
     * Checks if number of counts is bigger or smaller than current limit
     *
     * @return bool
     */
    public function check();

    /**
     * Get all abuse logs
     *
     * Returns all the abuse logs that are currently in the DB
     *
     * @return array
     */
    public function getAllLogs(): array;


    /**
     * Delete all logs older than $seconds seconds
     *
     * @param int $seconds
     * 
     * @return bool
     */
    public function deleteLogsOlderThan(int $seconds): bool;
}