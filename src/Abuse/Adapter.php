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
     * @param  int|null  $offset
     * @param  int|null  $limit
     * @return array<string, mixed>
     */
    public function getLogs(?int $offset, ?int $limit): array;

    /**
     * Delete all logs older than $datetime
     *
     * @param  string  $datetime
     * @return bool
     */
    public function cleanup(string $datetime): bool;
}
