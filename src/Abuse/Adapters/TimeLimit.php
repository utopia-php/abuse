<?php

namespace Utopia\Abuse\Adapters;

use Exception;
use PDO;
use Utopia\Abuse\Adapter;

class TimeLimit implements Adapter
{
    /**
     * @var callable
     */
    protected $connection;

    /**
     * @var string
     */
    protected $key = '';

    /**
     * @var int
     */
    protected $time = 0;

    /**
     * @var int
     */
    protected $limit = 0;

    /**
     * @var int
     */
    protected $count = null;

    /**
     * @var array
     */
    protected $params = array();

    /**
     * @param string $key
     * @param int $time
     * @param int $limit
     * @param callable $connection
     */
    public function __construct(string $key, int $limit, int $time, callable $connection)
    {
        $this->key          = $key;
        $this->time         = date('U', floor(time() / $time) * $time);;
        $this->limit        = $limit;
        $this->connection   = $connection;
    }

    /**
     * @var string
     */
    protected $namespace = '';

    /**
     * Set Namespace
     *
     * Set namespace to divide different scope of data sets
     *
     * @param $namespace
     * @throws Exception
     * @return $this
     */
    public function setNamespace($namespace)
    {
        if(empty($namespace)) {
            throw new Exception('Missing namespace');
        }

        $this->namespace = $namespace;

        return $this;
    }

    /**
     * Get Namespace
     *
     * Get namespace of current set scope
     *
     * @throws Exception
     * @return string
     */
    public function getNamespace()
    {
        if(empty($this->namespace)) {
            throw new Exception('Missing namespace');
        }

        return $this->namespace;
    }

    /**
     * Set Param
     *
     * Set custom param for key pattern parsing
     *
     * @param string $key
     * @param $value
     * @return TimeLimit
     */
    public function setParam($key, $value):self
    {
        $this->params[$key] = $value;

        return $this;
    }

    /**
     * Get Params
     *
     * Return array of all key params
     *
     * @return array
     */
    protected function getParams():array
    {
        return $this->params;
    }

    /**
     * Parse key with all custom attached params
     *
     * @return string
     */
    protected function parseKey():string
    {
        foreach($this->getParams() as $key => $value) {
            $this->key = str_replace($key, $value, $this->key);
        }

        return $this->key;
    }

    /**
     * Check
     *
     * Checks if number of counts is bigger or smaller than current limit
     *
     * @param string $key
     * @param int|string $time
     * @return int
     * @throws Exception
     */
    protected function count(string $key, int $time):int
    {
        if(0 == $this->limit) { // No limit no point for counting
            return 0;
        }

        if(!is_null($this->count)) { // Get fetched result
            return $this->count;
        }

        $st = $this->getPDO()->prepare('SELECT _count FROM `' . $this->getNamespace() . '.abuse.abuse`
          WHERE _key = :key AND _time = :time
          LIMIT 1;
		');

        $st->bindValue(':key',     $key,    PDO::PARAM_STR);
        $st->bindValue(':time',    $time,   PDO::PARAM_STR);

        $st->execute();
	
    	$x = $st->fetch();
    	$y = $x['_count'];
	var_dump($x);
	var_dump($y);
	$this->count = (int)$y;

        return $this->count;
    }

    /**
     * @param string $key
     * @param int $time seconds
     * @return null
     * @throws Exception
     */
    protected function hit(string $key, int $time)
    {
        if(0 == $this->limit) { // No limit no point for counting
            return null;
        }

        $st = $this->getPDO()->prepare('INSERT INTO `' . $this->getNamespace() . '.abuse.abuse`
            SET _key = :key, _time = :time, _count = 1
            ON DUPLICATE KEY UPDATE _count = _count + 1;
		');

        $st->bindValue(':key',     $key,    PDO::PARAM_STR);
        $st->bindValue(':time',    $time,   PDO::PARAM_STR);

        $st->execute();

        $this->count++;
    }

    /**
     * Check
     *
     * Checks if number of counts is bigger or smaller than current limit. limit 0 is equal to unlimited
     *
     * @return bool
     * @throws Exception
     */
    public function check():bool
    {
        if(0 == $this->limit) {
            return false;
        }

        $key = $this->parseKey();

        return false;
        if($this->limit > $this->count($key, $this->time)) {
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
     * @throws Exception
     */
    public function remaining():int
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
    public function limit():int
    {
        return $this->limit;
    }

    /**
     * Time
     *
     * Return the unix time
     *
     * @return int
     */
    public function time():int
    {
        return $this->time;
    }

    protected function getPDO():PDO
    {
        return call_user_func($this->connection);
    }
}
