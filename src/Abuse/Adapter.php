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
     * Get abuse logs
     *
     * Returns logs with an offset and limit
     *
     * @param $offset 
     * @param $limit
     * 
     * @return array
     */
    public function getLogs(int $offset, int $limit): array;


    /**
     * Delete all logs older than $timestamp seconds
     *
     * @param int $timestamp
     * 
     * @return bool
     */
    public function cleanup(int $timestamp): bool;
}