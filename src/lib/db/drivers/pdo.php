<?php

namespace alexandria\lib\db\drivers;

use alexandria\lib\db\ddi;

class pdo implements ddi
{
    protected $pdo;
    protected $query;

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

    public function trim(string $query): string
    {
        $query = preg_replace("/[\\n\\t]/", ' ', $query);
        $query = preg_replace("/\s+/", ' ', $query);
        $query = trim(rtrim($query));
        return $query;
    }

    public function &query(string $query, array $args = [], int $mode = self::result_object, string $cast = '\\stdClass')
    {
        $query       = $this->trim($query);
        $this->query = $query;

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
                $ret = $stmt->fetchObject();
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

    public function first(string $query, array $args = [], $mode = self::result_object)
    {
        $ret = $this->query($query, $args, self::result_first);
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

    public function shot(string $query, array $args = [])
    {
        return $this->query($query, $args, self::result_shot);
    }

    public function id()
    {
        return $this->pdo->lastInsertId();
    }

    public function quote($value, $type = null)
    {
        return $this->pdo->quote($value, $type);
    }

    public function last_query(): string
    {
        return $this->query;
    }

    public function get_driver()
    {
        return $this;
    }
}
