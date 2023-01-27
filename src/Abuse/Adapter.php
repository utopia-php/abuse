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
    public function check(): bool;

    /**
     * Get abuse logs
     *
     * Return logs with an offset and limit
     *
<<<<<<< HEAD
     * @param  int  $offset
     * @param  int  $limit
     * @param  int  $offset
     * @param  int  $limit
     * @return array<string, mixed>
     * @return array
>>>>>>> b27dfba4584aefab7e5434eadfb0befd2de2066c
     */
    public function getLogs(int $offset, int $limit): array;

    /**
     * Delete all logs older than $datetime
     *
     * @param  string  $datetime
     * @return bool
     */
    public function cleanup(string $datetime): bool;
}
