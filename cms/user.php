<?php

namespace alexandria\cms;

use alexandria\cms;
use alexandria\traits\properties;

class user
{
    use properties
    {
        _fill as protected _properties_fill;
    }

    protected $table = 'users';

    const access_banned     = -1;
    const access_disabled   = 0;
    const access_default    = 10;
    const access_user       = 15;
    const access_moderator  = 20;
    const access_extended   = 25;
    const access_privileged = 30;
    const access_admin      = 40;
    const access_root       = 42;

    protected $id;
    protected $login;
    protected $alias;
    protected $access;
    protected $password;
    public $data;

    public function __construct($args = null)
    {
        $this->__properties = [
            'id'       => PROPERTY_INT    | PROPERTY_READONLY,
            'login'    => PROPERTY_STRING | PROPERTY_READWRITE,
            'alias'    => PROPERTY_STRING | PROPERTY_READWRITE,
            'access'   => PROPERTY_INT    | PROPERTY_READWRITE,
            'password' => PROPERTY_STRING | PROPERTY_READWRITE,
            'data'     => PROPERTY_RAW    | PROPERTY_READWRITE,
        ];

	$this->data = new \stdClass;
        $this->__defaults([
            'access' => self::access_banned,
        ]);

        if (is_object($args) || is_array($args))
        {
            $this->_fill($args);
        }
        elseif (is_numeric($args))
        {
            $user = $this->by_id($args);
            if ($user)
            {
                $this->_fill($user->_data());
            }
        }
        elseif (is_string($args))
        {
            $user = $this->by_name($args);
            if ($user)
            {
                $this->_fill($user->_data());
            }
        }
    }

    public function _fill($args)
    {
        $this->_properties_fill($args);

        if (is_object($args))
        {
            $args = (array) $args;
        }

        if (!empty($args['data']) && is_scalar($args['data']))
        {
            $tmp = json_decode($args['data']);
            if (!is_null($tmp))
            {
                $this->data = $tmp;
            }
        }

        return $this;
    }

    public function exists(string $username): bool
    {
        $table = $this->table;

        $data = cms::db()->first("
			SELECT `id`
			FROM {$table}
			WHERE
			    `login` = :username
			    OR `alias` = :username", [
            ':username' => $username,
        ]);

        return !empty($data);
    }

    public function by_id(int $id)
    {
        $data = cms::db()->first("
			SELECT *
			FROM {$this->table}
			WHERE `id` = :id", [
            ':id' => $id,
        ]);

        if (empty($data))
        {
            return false;
        }

        return new static($data);
    }

    public function by_name(string $username)
    {
        $data = cms::db()->first("
			SELECT *
			FROM {$this->table}
			WHERE
			    `login` = :username
			    OR `alias` = :username", [
            ':username' => $username,
        ]);

        if (empty($data))
        {
            return false;
        }

        return new static($data);
    }

    public function save(): bool
    {
        $ret = null;

        // test password for hashvalue
        $info = password_get_info($this->password);
        if (empty($info['algo']))
        {
            $this->password = password_hash($this->password, PASSWORD_BCRYPT);
        }

        // encode data
        $data = json_encode($this->data, JSON_UNESCAPED_UNICODE);

        // new user
        if (!$this->id)
        {
            $ret = cms::db()->query("
                INSERT INTO {$this->table} (
                    `login`,
                    `alias`,
                    `password`,
                    `access`,
                    `data`
                ) VALUES (
                    :login,
                    :alias,
                    :password,
                    :access,
                    :data
                )",
                [
                    ':login'    => $this->login,
                    ':alias'    => $this->alias,
                    ':password' => $this->password,
                    ':access'   => $this->access,
                    ':data'     => $data,
                ]);

            $id = cms::db()->id();
            $this->id = $id;
        }

        else
        {
            $ret = cms::db()->query("
                UPDATE {$this->table}
                SET
                    `login` = :login,
                    `alias` = :alias,
                    `password` = :password,
                    `access` = :access,
                    `data` = :data
                WHERE `id` = :id",
                [
                    ':id'       => $this->id,
                    ':login'    => $this->login,
                    ':alias'    => $this->alias,
                    ':password' => $this->password,
                    ':access'   => $this->access,
                    ':data'     => $data,
                ]);
        }

        return $ret;
    }
}
