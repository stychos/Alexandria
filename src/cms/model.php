<?php

namespace alexandria\cms;

use alexandria\cms;
use alexandria\lib\db;
use alexandria\traits\properties;

/**
 * @property array properties
 */
class model
{
    use properties;

    /** @var db\ddi $db */
    protected $db;
    protected $table;
    protected $id_field         = 'id';
    protected $id_autoincrement = true;
    protected $_cache_id;

    protected static $_cache = [];

    /**
     * model constructor.
     *
     * @param mixed|null $data
     *
     * @throws \ReflectionException
     */
    public function __construct($data = null)
    {
        $this->db = cms::module('db');

        $classname = str_replace('\\', '_', get_called_class());
        $classname = strtolower($classname);
        $classname = preg_replace('~(\w+)_\1$~', '\1', $classname) . 's';

        $this->_cache_id = $classname;
        if (empty($this->table))
        {
            $this->table = $classname;
        }

        $this->properties = $this->properties ?? [];
        if (is_object($data) || is_array($data))
        {
            $this->fill($data);
        }

        self::$_cache[$this->_cache_id] = [];
    }

    /**
     * @return bool
     */
    public function save(): bool
    {
        $data = $this->data();

        // new record
        if (empty($data->{$this->id_field}))
        {
            $qdata = [];
            $vars  = [];
            foreach ($this->properties as $name => $_)
            {
                if ($name == $this->id_field)
                {
                    continue;
                }

                $qdata[]          = "`{$name}` = :{$name}";
                $vars[":{$name}"] = $data->$name;
            }

            $qdata = implode(', ', $qdata);
            $query = "INSERT INTO `{$this->table}` SET {$qdata}";
            $ret   = $this->db->query($query, $vars);

            if ($ret && $this->id_autoincrement)
            {
                // override possible readonly via fill()
                $this->fill([
                    $this->id_field => $this->db->id(),
                ]);
            }
        }

        // update exist record
        else
        {
            $qdata = [];
            $vars  = [
                ":{$this->id_field}" => $data->{$this->id_field},
            ];

            foreach ($this->properties as $name => $_)
            {
                $qdata[]          = "`{$name}` = :{$name}";
                $vars[":{$name}"] = $data->$name;
            }

            $qdata = implode(", ", $qdata);
            $query = "UPDATE `{$this->table}` SET {$qdata} WHERE `{$this->id_field}` = :id";
            $ret   = $this->db->query($query, $vars);
        }

        return $ret;
    }

    /**
     * @return bool
     */
    public function delete(): bool
    {
        $data = $this->data();
        $ret  = $this->db->query("
          DELETE FROM `{$this->table}`
          WHERE `{$this->id_field}` = :id", [
            ':id' => $data->{$this->id_field},
        ]);

        return $ret;
    }

    /**
     * @return string
     * @throws \ReflectionException
     */
    public static function table(): string
    {
        return (new static())->table;
    }

    /**
     * Usage:
     * find('field', 'value');
     * find('id', '>42');
     * find('group', '!25');
     * find('name', 'test%', [ 'limit' => '10, 20', 'order' => [ 'field', 'id desc' ]]);
     * find([ 'group' => 15, 'attr' => '1' ], [ 'limit' => 10, 'order' => 'id' ]);
     *
     * Opearors:
     * = equal
     * ! not equal
     * < less than
     * <= less or equal than
     * > greater than
     * >= greater or equal
     * ^ match with LIKE
     * ~ match with RLIKE
     *
     * @param string|array $arg1
     * @param null         $arg2
     * @param array        $arg3
     *
     * @return static[]
     * @throws \ReflectionException
     */
    public static function find($arg1, $arg2 = null, array $arg3 = []): array
    {
        $static = new static();
        $table  = $static->table;
        $db     = $static->db;

        $ret = [];
        $sql = "SELECT * FROM `{$table}` WHERE ";

        $fields = null;
        $qmasks = null;

        // search by id field
        if (is_scalar($arg1) && is_null($arg2))
        {
            $fields = [$static->id_field => $arg1];
        }

        // if field passed as scalar with value in the second argument and params in the third
        elseif (is_scalar($arg1) && is_scalar($arg2))
        {
            $fields = [$arg1 => $arg2];
            $params = $arg3;
        }

        // filds-values passed as array and params in the second argument
        elseif (is_array($arg1) && !empty($arg1))
        {
            $fields = $arg1;
            $params = is_array($arg2) ? $arg2 : [];
        }
        else
        {
            return [];
        }

        foreach ($fields as $field => $value)
        {
            preg_match('#^(?<operator>!|>=?|<=?|=|\~|\^)?\s?(?<value>.+)#', $value, $matches);
            $operator = $matches['operator'] ?? '=';
            if (empty($operator))
            {
                $operator = '=';
            }
            elseif ($operator == '^')
            {
                $operator = 'LIKE';
            }
            elseif ($operator == '~')
            {
                $operator = 'RLIKE';
            }

            $value = $matches['value'] ?? $value;

            $qmasks[":{$field}"] = $value;
            $sql                 .= "`{$field}` {$operator} :{$field} AND ";
        }
        $sql = preg_replace('~ AND $~', ' ', $sql);

        $order = $params['order'] ?? null;
        if ($order)
        {
            if (is_scalar($order))
            {
                preg_match('~^\s*(?<field>\w+)\s*(?<direction>asc|desc)$~i', $order, $matches);
                $direction = $matches['direction'] ?? 'ASC';
                $direction = strtoupper($direction);

                $field = $matches['field'] ?? null;
                if ($field)
                {
                    $sql .= "ORDER BY `{$field}` {$direction}, ";
                }
            }
            elseif (is_iterable($order))
            {
                $sql .= "ORDER BY ";
                foreach ($order as $_)
                {
                    preg_match('~^\s*(?<field>\w+)\s*(?<direction>asc|desc)$~i', $_, $matches);
                    $direction = $matches['direction'] ?? 'ASC';
                    $direction = strtoupper($direction);

                    $field = $matches['field'] ?? null;
                    if ($field)
                    {
                        $sql .= "`{$field}` {$direction}, ";
                    }
                }
            }
            $sql = preg_replace('~\, $~', ' ', $sql);
        }

        $limit = $params['limit'] ?? null;
        if (is_numeric($limit) || preg_match('~^\s*\d+(\,\s*\d+)?\s*$~', $limit))
        {
            $sql .= "LIMIT {$limit} ";
        }

        $data = $db->query($sql, $qmasks);
        foreach ($data as $item)
        {
            $ret [] = new static($item);
        }

        return $ret;
    }

    /**
     * @param      $arg1
     * @param null $arg2
     *
     * @return static|false
     * @throws \ReflectionException
     */
    public static function get($arg1, $arg2 = null): ?model
    {
        $fields = null;
        $params = ['limit' => 1];

        if (is_scalar($arg1) && is_scalar($arg2))
        {
            $fields = [$arg1 => $arg2];
        }
        elseif (is_array($arg1) && !empty($arg1))
        {
            $fields = $arg1;
        }
        else
        {
            return null;
        }

        $cache_id = (new static())->_cache_id;
        foreach (self::$_cache[$cache_id] as $cached)
        {
            $match = true;
            foreach ($fields as $name => $value)
            {
                if ($cached->$name != $value)
                {
                    $match = false;
                    break;
                }
            }

            if ($match)
            {
                return $cached;
            }
        }

        $data = self::find($fields, $params);
        if (!empty($data[0]))
        {
            self::$_cache [$cache_id] = $data[0];
            return $data[0];
        }

        return false;
    }

    /**
     * @return static[]
     * @throws \ReflectionException
     */
    public static function all(): array
    {
        $static = new static();
        $table  = $static->table;
        $db     = $static->db;
        unset($static);

        $ret  = [];
        $sql  = "SELECT * FROM `{$table}`";
        $data = $db->query($sql);
        foreach ($data as $item)
        {
            $ret [] = new static($item);
        }

        return $ret;
    }

    /**
     * @param      $arg1
     * @param null $arg2
     *
     * @return int|null
     * @throws \ReflectionException
     */
    public static function count($arg1, $arg2 = null): ?int
    {
        $static = new static();
        $table  = $static->table;
        $db     = $static->db;
        unset($static);

        $sql    = "SELECT COUNT(*) FROM `{$table}` WHERE ";
        $fields = null;
        $qmasks = null;

        // if field passed as scalar with value in the second argument and params in the third
        if (is_scalar($arg1) && is_scalar($arg2))
        {
            $fields = [$arg1 => $arg2];
        }

        // filds-values passed as array and params in the second argument
        elseif (is_array($arg1) && !empty($arg1))
        {
            $fields = $arg1;
        }
        else
        {
            return null;
        }

        foreach ($fields as $field => $value)
        {
            preg_match('#^(?<operator>!|>=?|<=?|=|\~|\^)?\s?(?<value>.+)#', $value, $matches);
            $operator = $matches['operator'] ?? '=';
            if (empty($operator))
            {
                $operator = '=';
            }
            elseif ($operator == '^')
            {
                $operator = 'LIKE';
            }
            elseif ($operator == '~')
            {
                $operator = 'RLIKE';
            }

            $value = $matches['value'] ?? $value;

            $qmasks[":{$field}"] = $value;
            $sql                 .= "`{$field}` {$operator} :{$field} AND ";
        }
        $sql = preg_replace('~ AND $~', ' ', $sql);

        $ret = $db->shot($sql, $qmasks);
        return $ret;
    }
}
