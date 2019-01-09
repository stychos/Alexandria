<?php

namespace alexandria;

/**
 * CMS registry
 */
class cms
{
    /** Takes classes configuration data and calls loaded classes with appropriate data from this vars */
    protected static $config;

    /** Tracks for loaded classes registry. All autoloaded classes calls via self::$class() processed trough single core-initiated instances */
    protected static $cache;

    /**
     * Starts CMS cycle.
     *
     * @param array $config Modules configuration in [ 'module' => $config ] associative array.
     * @throws \exception
     */
    public static function start(array $config = [])
    {
        self::$config = $config;

        if (empty(self::$config['router'])) {
            return;
        }

        // Pathinfo fix (on empty path nginx dont create this cgi variable)
        if (!isset($_SERVER['PATH_INFO'])) {
            $_SERVER['PATH_INFO'] = '';
        }

        if (!empty(self::$config['router']['path_rewrites'])) {
            self::module('uri')->add_aliases(self::$config['router']['path_rewrites']);
        }

        if (!stristr(PHP_SAPI, 'cli')) {
            self::module('theme');
        }

        if (empty(self::$config['router']['autoroute_path'])) {
            self::$config['router']['autoroute_path'] = self::module('uri')->all();
        }

        self::module('router', [self::$config['router']]);
        if (!empty(self::$config['router']['autoroute'])) {
            self::module('router')->autoroute();
        }

        if (!stristr(PHP_SAPI, 'cli')) {
            return self::module('theme')->render();
        }
    }

    /**
     * Load classes to the CMS registry or return loaded one
     *
     * @param string $module
     * @param array  $args
     * @param bool   $new_instance      Create new instance instead of cached one
     * @param bool   $exception_on_fail Raise exception if module was not found
     * @return mixed
     * @throws \ReflectionException
     */
    public static function module(string $module, ?array $args = [], bool $new_instance = false, bool $exception_on_fail = true)
    {
        $module = str_replace('/', '\\', $module);
        if (!empty(self::$cache[$module]) && !$new_instance) {
            return self::$cache[$module];
        }

        // Load config from starter if no variables passed
        if (empty($args)) {
            if (empty(self::$config[$module])) {
                $args = [];
            } else {
                $args = [self::$config[$module]];
            }
        }

        // Priority: Local > CMS > Libraries
        $try = [
            $module,
            $module.'\\controller',
            'alexandria\\cms\\'.$module,
            'alexandria\\lib\\'.$module,
        ];

        foreach ($try as $class) {
            if (class_exists($class)) {
                $reflect              = new \ReflectionClass($class);
                self::$cache[$module] = $reflect->newInstanceArgs($args);
                return self::$cache[$module];
            }
        }

        if ($exception_on_fail) {
            throw new \RuntimeException("Can not find module {$module}.");
        }
    }

    /**
     * @deprecated Using of magic accessors is strongly discouraged
     * @param string $module
     * @param array  $args
     * @return mixed
     * @throws \ReflectionException
     */
    public static function __callStatic(string $module, array $args = [])
    {
        return self::module($module, $args);
    }
}
