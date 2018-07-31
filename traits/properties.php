<?php
/**
 * Getters, Setters, Properties
 */

namespace alexandria\traits;

define('PROPERTY_PRIVATE', 0);
define('PROPERTY_READWRITE', 1);
define('PROPERTY_READONLY', 2);
define('PROPERTY_WRITEONLY', 4);

define('PROPERTY_RAW', 0);
define('PROPERTY_BOOL', 16);
define('PROPERTY_INT', 32);
define('PROPERTY_FLOAT', 64);
define('PROPERTY_STRING', 128);

trait properties
{
    protected $__properties;
    protected $__defaults;

    protected function __properties(array $properties = [])
    {
        foreach ($properties as $name => $params)
        {
            $this->__properties[$name] = $params;
        }
    }

    protected function __defaults(array $properties = [])
    {
        foreach ($properties as $name => $value)
        {
            $this->__defaults[$name] = $value;
        }
    }

    protected function _cast(string $property, $value, $null_on_fail = false)
    {
        if (empty($this->__properties[$property]))
        {
            return $null_on_fail ? null : $value;
        }

        $cfg = $this->__properties[$property];
        if ($cfg & PROPERTY_BOOL)
        {
            return (bool) $value;
        }
        elseif ($cfg & PROPERTY_INT)
        {
            return (int) $value;
        }
        elseif ($cfg & PROPERTY_FLOAT)
        {
            return (float) $value;
        }
        elseif ($cfg & PROPERTY_STRING)
        {
            return (string) $value;
        }

        // untyped property, return as is
        return $value;
    }

    protected function _clear()
    {
        foreach ($this->__properties as $property => $_)
        {
            if (isset($this->__defaults[$property]))
            {
                $this->$property = $this->_cast($property, $this->__defaults[$property]);
            }
            else
            {
                $this->$property = null;
            }
        }

        return $this;
    }

    protected function _fill($properties = [])
    {
        if (is_object($properties))
        {
            $properties = (array) $properties;
        }

        if (!is_array($properties))
        {
            throw new \InvalidArgumentException("_fill() can accept arrays or objects only");
        }

        foreach ($this->__properties as $property => $_)
        {
            if (isset($properties[$property]))
            {
                $this->$property = $this->_cast($property, $properties[$property]);
            }

            elseif (isset($this->__defaults[$property]))
            {
                $this->$property = $this->_cast($property, $this->__defaults[$property]);
            }
        }

        return $this;
    }

    public function _data(): \stdClass
    {
        $ret = new \stdClass;
        foreach ($this->__properties as $name => $v)
        {
            $ret->$name = $this->$name;
        }

        return $ret;
    }

    public function __get($property)
    {
        $trace = debug_backtrace();

        if (isset($this->__properties[$property])
            && ($this->__properties[$property] & PROPERTY_WRITEONLY))
        {
            $msg = sprintf('Cannot access writeonly property: %s::%s', $trace[0]['class'], $property);
            trigger_error($msg, E_USER_ERROR);
            return null;
        }

        elseif (empty($this->__properties[$property]) ||
                !($this->__properties[$property] & PROPERTY_READONLY ||
                  $this->__properties[$property] & PROPERTY_READWRITE))
        {
            $reflect    = new \ReflectionObject($this);
            $properties = $reflect->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED);

            foreach ($properties as $p)
            {
                if ($p->getName() === $property)
                {
                    $msg = sprintf('Cannot access protected property: %s::%s', $trace[0]['class'], $property);
                    trigger_error($msg, E_USER_ERROR);
                    return null;
                }
            }

            $msg = sprintf('Undefined property: %s::%s', $trace[0]['class'], $property);
            trigger_error($msg, E_USER_NOTICE);
            return null;
        }

        return $this->_cast($property, $this->$property);
    }

    public function __set($property, $value)
    {
        $trace = debug_backtrace();
        if (!empty($this->__properties[$property]) &&
            ($this->__properties[$property] & PROPERTY_WRITEONLY ||
             $this->__properties[$property] & PROPERTY_READWRITE))
        {
            $this->$property = $this->_cast($property, $value);
            return;
        }

        elseif ($this->__properties[$property] & PROPERTY_READONLY)
        {
            $msg = sprintf('Cannot write read-only property: %s::%s', $trace[0]['class'], $property);
            trigger_error($msg, E_USER_ERROR);
            return;
        }

        else
        {
            $reflect    = new \ReflectionObject($this);
            $properties = $reflect->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED);

            foreach ($properties as $p)
            {
                if ($p->getName() === $property)
                {
                    $msg = sprintf('Cannot write protected property: %s::%s', $trace[0]['class'], $property);
                    trigger_error($msg, E_USER_ERROR);
                    return;
                }
            }
        }

        $this->$property = $this->_cast($property, $value);
    }

    public function __isset($property): bool
    {
        return isset($this->$property);
    }
}
