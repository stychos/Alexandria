<?php

namespace alexandria\cms;

use alexandria\cms;

class config extends cms
{
    protected $data;
    protected $table = 'config';
    protected $table_exists;

    public function __construct()
    {
        // 1. read config values from CMS starter
        foreach (self::$config as $name => $value) {
            $this->data[$name] = (object) [
                'type'  => gettype($value),
                'value' => $value,
            ];
        }

        try {
            $this->table_exists = cms::db()->query("SELECT 1 FROM {$this->table} LIMIT 1");
        } catch (\throwable $e) {
            $this->table_exists = false;
        }

        // 2. read config values from database (if configured)
        if ($this->table_exists) {
            // 2. read & override from database
            $data = cms::db()->query("
            SELECT *
            FROM {$this->table}");

            foreach ($data as $index => $v) {
                $value = (in_array($v->type, ['object', 'mixed', 'array']))
                    ? json_decode($v->value)
                    : $v->value;

                $this->data[$v->name] = (object) [
                    'type'  => $v->type,
                    'value' => $value,
                ];
            }
        }
    }

    public function __isset($name)
    {
        return (isset($data[$name]));
    }

    public function __get($name)
    {
        if (isset($this->data[$name]->value)) {
            return $this->data[$name]->value;
        }

        return false;
    }

    public function __set($name, $value)
    {
        $this->data[$name]->type  = gettype($value);
        $this->data[$name]->value = !is_scalar($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : $value;

        if ($this->table_exists) {
            cms::db()->query("
            REPLACE INTO {$this->table} (`name`, `type`, `value`)
            VALUES (:name, :type, :value)", [
                ':name'  => $name,
                ':type'  => $this->data[$name]->type,
                ':value' => $this->data[$name]->value,
            ]);
        }
    }

    public function __dump()
    {
        return $this->data;
    }
}
