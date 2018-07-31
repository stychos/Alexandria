<?php

namespace alexandria\traits;

/**
 * Singleton trait.
 * Nowadays this is an antipattern.
 */
trait singleton
{
    protected static $__singleton;

    final public static function instance()
    {
        return isset(static::$__singleton)
            ? static::$__singleton
            : static::$__singleton = new static;
    }

    final private function __wakeup() {}
    final private function __clone() {}
    final private function __construct()
    {
        $this->__singleton();
    }

    protected function __singleton() {}
}
