<?php

namespace alexandria\lib\db\drivers;

use alexandria\lib\db\ddi;

class pdo implements ddi
{
    protected $pdo;
    protected $query;

    /**
     * pdo constructor.
     *
     * @param $args
     */
    public function __construct($args)
    {
        if (empty($args['dsn']))
        {
            Throw new \RuntimeException('You must specify DSN for using database');
        }

        $username  = $args['username'] ?? null;
        $password  = $args['password'] ?? null;
        $options   = $args['options'] ?? null;
        $this->pdo = new \PDO($args['dsn'], $username, $password, $options);
    }

    /**
     * @param string $query
     * @param array  $args
     * @param int    $mode
     * @param string $cast
     *
     * @return array|bool|mixed
     */
    public function &query(string $query, array $args = [], int $mode = self::result_object, string $cast = '\\stdClass')
    {
        $ret = false;

        $query = preg_replace("/ +/", ' ', $query);
        $query = str_replace("\t", ' ', $query);
        $query = trim(rtrim($query));

        if (empty($query))
        {
            return $ret;
        }
        else
        {
            $this->query = $query;
        }

        if (empty($mode))
        {
            $mode = self::result_object;
        }

        $stmt = $this->pdo->prepare($query);
        if (!$stmt)
        {
            Throw new \RuntimeException("Error preparing statement: {$stmt->errorInfo()[0]}, {$stmt->errorInfo()[1]}. {$stmt->errorInfo()[2]}.");
        }

        $ret = $stmt->execute($args);
        if (!$ret)
        {
            Throw new \RuntimeException("Error executing statement: {$stmt->errorInfo()[0]}, {$stmt->errorInfo()[1]}. {$stmt->errorInfo()[2]}.");
        }

        if (!preg_match("/^\s*SELECT|SHOW|DESCRIBE|EXPLAIN/i", $query))
        {
            return $ret;
        }

        switch ($mode)
        {
            case self::result_assoc:
                $ret = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            break;

            case self::result_numeric:
                $ret = $stmt->fetchAll(\PDO::FETCH_NUM);
            break;

            case self::result_first:
                $ret = $stmt->fetchObject($cast);
            break;

            case self::result_shot:
                $ret = $stmt->fetch(\PDO::FETCH_NUM)[0];
            break;

            default:
                $ret = $stmt->fetchAll(\PDO::FETCH_CLASS, $cast);
            break;
        }

        return $ret;
    }

    /**
     * @param string $query
     * @param array  $args
     * @param int    $mode
     * @param string $cast
     * @return array
     */
    public function multi(string $query, array $args = [], int $mode = self::result_object, string $cast = '\\stdClass')
    {
        $ret     = [];
        $queries = explode(';', $query);
        foreach ($queries as $query)
        {
            $query = trim(rtrim($query));
            if (empty($query))
            {
                continue;
            }

            $ret[$query] = $this->query($query, $args, $mode, $cast);
        }

        return $ret;
    }

    /**
     * @param string $query
     * @param array  $args
     * @param int    $mode
     * @param string $cast
     *
     * @return array|bool|mixed
     */
    public function first(string $query, array $args = [], int $mode = self::result_object, string $cast = '\\stdClass')
    {
        $ret = $this->query($query, $args, self::result_first, $cast);
        if (is_object($ret))
        {
            switch ($mode)
            {
                case self::result_assoc:
                    $ret = (array) $ret;
                break;

                case self::result_numeric:
                    $ret = array_values((array) $ret);
                break;
            }
        }

        return $ret;
    }

    /**
     * @param string $query
     * @param array  $args
     *
     * @return array|bool|mixed
     */
    public function shot(string $query, array $args = [])
    {
        return $this->query($query, $args, self::result_shot);
    }

    /**
     * @return string
     */
    public function id()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * @param      $value
     * @param null $type
     *
     * @return string
     */
    public function quote($value, $type = null)
    {
        return $this->pdo->quote($value, $type);
    }

    /**
     * @return string
     */
    public function last_query(): string
    {
        return $this->query;
    }

    /**
     * @return $this
     */
    public function driver()
    {
        return $this;
    }

    public function table_exists(string $table): bool
    {
        try
        {
            $this->query("SELECT 1 FROM {$table} LIMIT 1");
        }
        catch (\throwable $e)
        {
            return false;
        }

        return true;
    }
}
