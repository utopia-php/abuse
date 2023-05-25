<?php

namespace Utopia\Abuse;

class Abuse
{
    /**
     * @var Adapter
     */
    protected Adapter $adapter;

    /**
     * @param  Adapter  $adapter
     */
    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Check
     *
     * Checks if request is considered abuse or not
     *
     * @return bool
     * @deprecated Check is ambiguous, use isSafe instead
     */
    public function check(): bool
    {
        return $this->adapter->check();
    }

    /**
     * Main method for threat detection
     *
     * @return bool Returns true if is safe to continue
     */
    public function isSafe(): bool
    {
        return $this->adapter->isSafe();
    }

    /**
     * Get abuse logs
     *
     * Return logs with an offset and limit
     *
     * @param  int|null  $offset
     * @param  int|null  $limit
     * @return array<string, mixed>
     */
    public function getLogs(?int $offset = null, ?int $limit = 25): array
    {
        return $this->adapter->getLogs($offset, $limit);
    }

    /**
     * Delete all logs older than $datetime
     *
     * @param  string  $datetime
     * @return bool
     */
    public function cleanup(string $datetime): bool
    {
        return $this->adapter->cleanup($datetime);
    }
}
