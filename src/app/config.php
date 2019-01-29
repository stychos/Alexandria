<?php

namespace alexandria\app;

use alexandria\lib\db\ddi;

class config
{
    /** @var ddi */
    protected $_db;
    protected $_use_db;
    protected $_table = 'config';
    protected $_data;


    /**
     * config constructor.
     *
     * @param array|null $data
     */
    public function __construct(array $data = [])
    {
        if (!empty($data['db']))
        {
            $this->_db     = kernel::load('db', $data['db']);
            $this->_use_db = $this->_db && $this->_db->table_exists($this->_table);
        }

        // 1. read config values from CMS starter
        foreach ($data as $name => $value)
        {
            $this->_data[$name] = (object) [
                'type'  => gettype($value),
                'value' => $value,
            ];
        }

        // 2. read config values from database (if configured)
        if ($this->_use_db)
        {
            // 2. read & override from database
            $data = $this->_db->query("
              SELECT *
              FROM {$this->_table}");

            foreach ($data as $index => $v)
            {
                $value = (in_array($v->type, ['object', 'mixed', 'array'])) ? json_decode($v->value) : $v->value;

                $this->_data[$v->name] = (object) [
                    'type'  => $v->type,
                    'value' => $value,
                ];
            }
        }
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return (isset($this->_data[$name]));
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function __get($name)
    {
        if (isset($this->_data[$name]->value))
        {
            return $this->_data[$name]->value;
        }

        return false;
    }

    /**
     * @param string $name
     * @param mixed  $value
     */
    public function __set($name, $value)
    {
        $this->_data[$name]->type  = gettype($value);
        $this->_data[$name]->value = !is_scalar($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;

        if ($this->_use_db)
        {
            $this->_db->query("
            REPLACE INTO {$this->_table} (`name`, `type`, `value`)
            VALUES (:name, :type, :value)", [
                ':name'  => $name,
                ':type'  => $this->_data[$name]->type,
                ':value' => $this->_data[$name]->value,
            ]);
        }
    }

    /**
     * @return array
     */
    public function &__dump()
    {
        return $this->_data;
    }
}
