<?php

namespace alexandria\traits;

/**
 * Properties, reinforced
 */

trait properties
{
    /**
     * Parse property declaration into the property configuration object
     *
     * Declaration keys are: type, access and default property value
     * Property declaration keywords separated by the whitespaces
     * Default property value must be an evaluable expression after the colon
     * Everything after the first colon is meant to be the default value (if present)
     *
     * For example:
     *   int : 42
     *   readonly float : 25.625
     *   string writeonly : ' '
     *   array readwrite default: [1,2,3,4,5]
     *
     * @param  string $config Property config to parse
     * @return object         Configuration object
     */
    protected function _property_parse(string $config)
    {
        $type    = null;
        $access  = null;
        $default = null;

        $chunks = explode(':', $config);
        $config = trim(array_shift($chunks));
        if (count($chunks)) {
            $default = implode(':', $chunks);
        }

        foreach (preg_split('~\s+~', $config) as $_) {
            $chunk = strtolower($_);
            if (in_array($chunk, [
                'bool', 'boolean', 'int', 'integer',
                'float', 'double', 'string',
                'array', 'object',
            ])) {
                $type = $chunk;
            } elseif (in_array($chunk, [
                'readonly', 'readwrite', 'writeonly',
            ])) {
                $access = $chunk;
            }
        }

        if (!empty($default)) {
            $tmp = null;
            if ($type == 'array') {
                try {
                    $tmp = eval("return {$default};");
                } catch (\throwable $e) {
                    $tmp = json_decode($default);
                    if (is_object($tmp)) {
                        $tmp = (array) $tmp;
                    }
                }

                if (is_null($tmp)) {
                    $tmp = [];
                }
                $default = $tmp;
            } elseif ($type == 'object') {
                try {
                    $tmp = eval("return {$default};");
                } catch (\throwable $e) {
                    $tmp = json_decode($default);
                }

                if (is_null($tmp)) {
                    $tmp = new \stdClass;
                }
                $default = $tmp;
            } else {
                $default = $this->_property_cast($default, $type);
            }
        }

        return (object) [
            'type'    => $type,
            'access'  => $access,
            'default' => $default,
        ];
    }

    /**
     * Cast variable into the target type if possible
     *
     * @param  mixed      $value  Variable to cast
     * @param  string     $type   Target type
     * @return mixed|null         Returns translated variable or null
     */
    protected function _property_cast($value, string $type = null)
    {
        $ret = null;

        switch ($type) {
            case 'bool':
            case 'boolean':
                if (is_scalar($value)) {
                    $ret = (bool) $value;
                }
            break;

            case 'int':
            case 'integer':
                if (is_scalar($value)) {
                    $ret = (int) $value;
                }
            break;

            case 'float':
            case 'double':
                if (is_scalar($value)) {
                    $ret = (float) $value;
                }
            break;

            case 'string':
                if (is_scalar($value)) {
                    $ret = (string) $value;
                }
            break;

            case 'array':
                if (is_array($value)) {
                    $ret = $value;
                } elseif (is_object($value)) {
                    $ret = (array) $value;
                } elseif (is_string($value)) {
                    $ret = (array) json_decode($value);
                }

                if (is_null($ret)) {
                    $ret = [];
                }
            break;

            case 'object':
                if (is_object($value)) {
                    $ret = $value;
                } elseif (is_array($value)) {
                    $ret = (object) $value;
                } elseif (is_string($value)) {
                    $ret = json_decode($value);
                }

                if (is_null($ret)) {
                    $ret = new \stdClass;
                }
            break;

            default:
                $ret = $value;
            break;
        }

        return $ret;
    }

    /**
     * Magic method to check if property is declared in class
     * @param  string  $name Property name to check
     * @return boolean       True if property is declared, otherwise false
     */
    public function __isset($name): bool
    {
        $reflect    = new \ReflectionObject($this);
        $properties = $reflect->getProperties();

        foreach ($properties as $_) {
            if ($name === $_->getName()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Magic method to get properties according to configuration specs
     * @param  string $name Property name
     * @return mixed        Returns property value or it's default value (if configured and current is null)
     */
    public function __get($name)
    {
        $class = __CLASS__;

        // called property not in configured list
        if (!isset($this->properties[$name])) {
            $reflect    = new \ReflectionObject($this);
            $properties = $reflect->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED);

            foreach ($properties as $_) {
                if ($name === $_->getName()) {
                    trigger_error("Cannot access protected property: {$class}::{$name}", E_USER_ERROR);
                    return null;
                }
            }

            trigger_error("Undefined property: {$class}::{$name}", E_USER_NOTICE);
            return null;
        }

        // property is configured, check for the writeonly flag
        $cfg = $this->_property_parse($this->properties[$name]);
        if ($cfg->access == 'writeonly') {
            trigger_error("Cannot access writeonly property: {$class}::{$name}", E_USER_ERROR);
            return null;
        }

        // cast to configured type
        if (is_null($this->$name) && !is_null($cfg->default)) {
            $this->$name = $cfg->default;
        }

        return $this->$name;
    }

    /**
     * Magic method to set properties declared as writeable
     * @param string $name   Property name to set
     * @param mixed  $value  Value to set
     */
    public function __set($name, $value)
    {
        $class = __CLASS__;
        if (isset($this->properties[$name])) {
            $cfg = $this->_property_parse($this->properties[$name]);
            if ($cfg->access == 'readonly') {
                trigger_error("Cannot write read-only property: {$class}::{$name}", E_USER_ERROR);
                return;
            }

            $this->$name = $this->_property_cast($value, $cfg->type);
        } else {
            $reflect    = new \ReflectionObject($this);
            $properties = $reflect->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED);

            foreach ($properties as $_) {
                if ($name === $_->getName()) {
                    trigger_error("Cannot write protected name: {$class}::{$name}", E_USER_ERROR);
                    return;
                }
            }

            $this->$name = $value;
        }
    }

    /**
     * Clean all declared properties
     * @return self
     */
    public function clean()
    {
        foreach ($this->properties as $name => $_) {
            $this->$name = null;
        }

        return $this;
    }

    /**
     * Fill declared properties from array or object
     * @param  array|object $data data to fill into declared properties
     * @return self
     */
    public function fill($data)
    {
        if (is_object($data)) {
            $data = (array) $data;
        }

        if (is_array($data)) {
            foreach ($data as $name => $value) {
                if (isset($this->properties[$name])) {
                    $cfg = $this->_property_parse($this->properties[$name]);
                    $this->$name = $this->_property_cast($value, $cfg->type);
                } else {
                    $this->$name = $value;
                }
            }
        }

        return $this;
    }

    /**
     * Retreive all declared properties as object,
     * array- and object-typed properties returns serialized with JSON
     * @param  int       $json_flags Encode serializable properties with these JSON flags
     * @return \stdClass
     */
    public function data(int $json_flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT): \stdClass
    {
        $ret = new \stdClass;
        foreach ($this->properties as $name => $_) {
            $cfg   = $this->_property_parse($_);
            $value = $this->$name;
            if (is_null($value) && $cfg->default) {
                $value = $cfg->default;
            }

            if (in_array($cfg->type, ['array', 'object'])) {
                $ret->$name = json_encode($value, $json_flags);
            } else {
                $ret->$name = $value;
            }
        }

        return $ret;
    }
}
