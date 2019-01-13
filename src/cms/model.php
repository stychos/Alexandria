<?php

namespace alexandria\cms;

use alexandria\cms;
use alexandria\traits\properties;

class model
{
    use properties;

    protected $db;
    protected $table;
    protected $id_field = 'id';
    protected $id_autoincrement = true;

    public function __construct($data = null)
    {
        $this->db = cms::module('db');
        $this->properties = $this->properties ?? [];

        $path = explode('\\', get_called_class());
        $class = array_pop($path);
        $this->table = $this->table ?? strtolower($class).'s';

        if (is_object($data) || is_array($data)) {
            $this->fill($data);
        }
    }

    public function save(): bool
    {
        $vars = [];
        $data = $this->data();

        // new record
        if (empty($data->{$this->id_field})) {
            $query = "INSERT INTO `{$this->table}` SET ";
            foreach ($this->properties as $name => $_) {
                if ($name == $this->id_field) {
                    continue;
                }

                $query .= "`{$name}` = :{$name}, ";
                $vars[":{$name}"] = $data->$name;
            }

            $query = preg_replace('~\, $~', '', $query); // fix last comma
            $ret = $this->db->query($query, $vars);
            if ($ret && $this->id_autoincrement) {
                $this->{$this->id_field} = $this->db->id();
            }
        }

        // update exist record
        else {
            $query = "UPDATE `{$this->table}` SET ";
            foreach ($this->properties as $name => $_) {
                $query .= "`{$name}` = :{$name}, ";
                $vars[":{$name}"] = $data->$name;
            }

            $query = preg_replace('~\, $~', ' ', $query); // fix last comma
            $query .= "WHERE `{$this->id_field}` = :id";
            $vars[':id'] = $data->{$this->id_field};

            $ret = $this->db->query($query, $vars);
        }

        return $ret;
    }

    public function delete()
    {
        $data = $this->data();
        $ret = $this->db->query("
            DELETE FROM `{$this->table}`
            WHERE `{$this->id_field}` = :id", [
                ':id' => $data->{$this->id_field} ]);

        return $ret;
    }

    public static function table()
    {
        return (new static)->table;
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
     */
    public static function find($arg1, $arg2 = null, array $arg3 = []): array
    {
        $static = new static;
        $table  = $static->table;
        $db     = $static->db;
        unset($static);

        $ret = [];
        $sql = "SELECT * FROM `{$table}` WHERE ";

        $fields = null;
        $qmasks = null;

        // if field passed as scalar with value in the second argument and params in the third
        if (is_scalar($arg1) && is_scalar($arg2)) {
            $fields = [ $arg1 => $arg2 ];
            $params = $arg3;
        }

        // filds-values passed as array and params in the second argument
        elseif (is_array($arg1) && !empty($arg1)) {
            $fields = $arg1;
            $params = is_array($arg2) ? $arg2 : [];
        } else {
            return [];
        }

        foreach ($fields as $field => $value) {
            $value = str_replace('~', '\~', $value);
            preg_match('~^(?<operator>!|>=?|<=?|=|^|\~)?\s?(?<value>.+)~', $value, $matches);
            $operator = $matches['operator'] ?? '=';
            if (empty($operator)) {
                $operator = '=';
            } elseif ($operator == '^') {
                $operator = 'LIKE';
            } elseif ($operator == '~') {
                $operator = 'RLIKE';
            }

            $value = $matches['value'] ?? $value;

            $qmasks[":{$field}"] = $value;
            $sql .= "`{$field}` {$operator} :{$field} AND ";
        }
        $sql = preg_replace('~ AND $~', ' ', $sql);

        $order = $params['order'] ?? null;
        if ($order) {
            if (is_scalar($order)) {
                $sql .= "ORDER BY `{$order}` ";
            } elseif (is_iterable($order)) {
                $sql .= "ORDER BY ";
                foreach ($order as $_) {
                    preg_match('~^\s*(?<field>\w+)\s*(?<direction>asc|desc)$~i', $_, $matches);
                    $direction = $matches['direction'] ?? 'asc';
                    $direction = strtoupper($direction);
                    $field = $matches['field'] ?? $_;
                    $sql .= "`{$field}` {$direction}, ";
                }
            }
            $sql = preg_replace('~\, $~', ' ', $sql);
        }

        $limit = $params['limit'] ?? null;
        if ($limit
        && (is_numeric($limit) || preg_match('~^\s*\d+(\,\s*\d+)?\s*$~', $limit))) {
            $sql .= "LIMIT {$limit} ";
        }

        $data = $db->query($sql, $qmasks);
        foreach ($data as $item) {
            $ret []= new static($item);
        }

        return $ret;
    }

    public static function get($arg1, $arg2 = null)
    {
        $fields = null;
        $params = [ 'limit' => 1 ];

        if (is_scalar($arg1) && is_scalar($arg2)) {
            $fields = [ $arg1 => $arg2 ];
        } elseif (is_array($arg1) && !empty($arg1)) {
            $fields = $arg1;
        } else {
            return false;
        }

        $data = self::find($fields, $params);
        return $data[0] ?? false;
    }

    public static function all()
    {
        $static = new static;
        $table  = $static->table;
        $db     = $static->db;
        unset($static);

        $ret = [];
        $sql = "SELECT * FROM `{$table}`";
        $data = $db->query($sql);
        foreach ($data as $item) {
            $ret []= new static($item);
        }

        return $ret;
    }
}
