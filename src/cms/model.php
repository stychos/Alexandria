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

    public function __construct($data)
    {
        $this->db = cms::module('db');

        $path = explode('\\', get_called_class());
        $class = array_pop($path);
        $this->table = strtolower($class).'s';

        if (is_object($data) || is_array($data)) {
            $this->fill($data);
        }
    }

    public function save(): bool
    {
        $vars = [];
        $data = $this->data();

        // new record
        if (empty($this->id_field)) {
            $query = "INSERT INTO `{$this->table}` SET ";
            foreach ($this->properties as $name => $_)
            {
                if ($name == $this->id_field) {
                    continue;
                }

                $query .= "`{$name}` = :{$name}, ";
                $vars[":{$name}"] = $data->$name;
            }

            $query = preg_replace('~\, $~', '', $query); // fix last comma
            $ret = $this->db->query($query, $vars);
            if ($ret && $this->id_autoincrement) {
                $this->$id_field = $this->db->id();
            }
        }

        // update exist record
        else {
            $query = "UPDATE `{$this->table}` SET ";
            foreach ($this->properties as $name => $_)
            {
                if ($name == $this->id_field) {
                    continue;
                }

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
}
