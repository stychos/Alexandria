<?php

namespace alexandria\lib;

/**
 * Class db
 * @package alexandria\lib
 * @method query(string $query, array $args = [], string $mode = db\ddi::result_object, string $cast = '\\stdClass')
 */
class db
{
    public $stats;

    protected $driver;

    public function __construct($args)
    {
        $stamp = microtime(true);
        $this->stats = (object) [
            'queries' => 0,
            'time' => 0,
        ];

        if (is_object($args)) {
            $args = (array) $args;
        }

        if (empty($args['driver'])) {
            throw new \InvalidArgumentException('Driver can not be empty.');
        }

        try {
            $reflect = new \ReflectionClass(__NAMESPACE__.'\\db\\drivers\\'.$args['driver']);
            $this->driver = $reflect->newInstance($args);
        } catch (\Exception $e) {
            throw new \RuntimeException("Driver error: {$e->getMessage()}");
        }

        $this->stats->time += microtime(true) - $stamp;
    }

    /**
     * @param $func
     * @param $args
     * @return mixed
     * @throws \ReflectionException
     */
    public function __call($func, $args)
    {
        $stamp = microtime(true);
        $reflect = new \ReflectionMethod($this->driver, $func);
        $ret = $reflect->invokeArgs($this->driver, $args);

        $this->stats->time += microtime(true) - $stamp;
        $this->stats->queries++;
        return $ret;
    }

    public function get_driver()
    {
        return $this->driver;
    }
}
