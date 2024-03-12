<?php

namespace Utopia\Abuse\Adapters;

use Utopia\Abuse\Adapter;
use Redis as Client;

class Redis implements Adapter
{
    public const NAMESPACE = 'abuse';

    /**
     * @var Client
     */
    protected Client $redis;

    /**
     * @var string
     */
    protected string $key = '';

    /**
     * @var string
     */
    protected string $time;

    /**
     * @var int
     */
    protected int $limit = 0;

    /**
     * @var int|null
     */
    protected ?int $count = null;

    /**
     * @var array<string, string>
     */
    protected array $params = [];


    public function __construct(string $key, int $limit, int $seconds, Client $redis)
    {
        $this->redis = $redis;
        $this->key = $key;
        $time = (int) \date('U', (int) (\floor(\time() / $seconds)) * $seconds); // todo: any good Idea without time()?
        $this->time = $time;
        $this->limit = $limit;
    }

    /**
     * Set Param
     *
     * Set custom param for key pattern parsing
     *
     * @param  string  $key
     * @param  string  $value
     * @return $this
     */
    public function setParam(string $key, string $value): self
    {
        $this->params[$key] = $value;

        return $this;
    }

    /**
     * Get Params
     *
     * Return array of all key params
     *
     * @return array<string, string>
     */
    protected function getParams(): array
    {
        return $this->params;
    }

    /**
     * Parse key with all custom attached params
     *
     * @return string
     */
    protected function parseKey(): string
    {
        foreach ($this->getParams() as $key => $value) {
            $this->key = \str_replace($key, $value, $this->key);
        }

        return $this->key;
    }

    protected function count(string $key, string $datetime): int
    {
        if (0 == $this->limit) { // No limit no point for counting
            return 0;
        }

        if (! \is_null($this->count)) { // Get fetched result
            return $this->count;
        }

        $this->count = $this->redis->get(self::NAMESPACE . ':' . $key .':' . $datetime);

        return $this->count;
    }

    /**
     * @param  string  $key
     * @param  string  $datetime
     * @return void
     *
     */
    protected function hit(string $key, string $datetime): void
    {
        if (0 == $this->limit) { // No limit no point for counting
            return;
        }

        $this->count = $this->redis->get(self::NAMESPACE . ':'. $key .':'. $datetime) ?? 0;

        $this->redis->incr(self::NAMESPACE . ':'. $key .':'. $datetime);
        $this->count++;
    }

    /**
     * Check
     *
     * Checks if number of counts is bigger or smaller than current limit
     *
     * @return bool
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
        // TODO limit potential is SCAN but needs cursor no offset
        return $this->redis->keys(self::NAMESPACE . ':*'); 
    }

    /**
     * Delete all logs older than $datetime
     *
     * @param  string  $datetime
     * @return bool
     */
    public function cleanup(string $datetime): bool
    {
        // TODO
        $iterator = NULL;
        while($iterator !== 0) {
            $keys = $this->redis->scan($iterator, self::NAMESPACE . ':*:' . $datetime);
            var_dump($keys);
            $this->redis->del($keys);
        }
        return true;
    }

}