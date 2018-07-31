<?php

namespace alexandria;

// Pathinfo fix (on empty path nginx dont create this cgi variable)
if (!isset($_SERVER['PATH_INFO']))
{
    $_SERVER['PATH_INFO'] = '';
}

/**
 * CMS registry
 *
 * @method cms\config config() static
 * @method cms\theme theme() static
 * @method cms\user user() static
 * @method cms\login login() static
 *
 * @method lib\autoload autoload() static
 * @method lib\db\ddi db() static
 * @method lib\docker docker() static
 * @method lib\form form() static
 * @method lib\firewall firewall() static
 * @method lib\http http() static
 * @method lib\request request() static
 * @method lib\response response() static
 * @method lib\router router() static
 * @method lib\security security() static
 * @method lib\smtp smtp() static
 * @method lib\ssh ssh() static
 * @method lib\uploads uploads() static
 * @method lib\uri uri() static
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
     *
     * @return void This function does not return anything.
     * @throws \exception
     */
    public static function start(array $config = [])
    {
        self::$config = $config;

        if (!empty(self::$config['firewall']))
        {
            self::firewall(self::$config['firewall']);
        }

        if (empty(self::$config['router']))
        {
            return;
        }

        if (!empty(self::$config['router']['path_rewrites']))
        {
            self::uri()->add_aliases(self::$config['router']['path_rewrites']);
        }

        if (!stristr(PHP_SAPI, 'cli'))
        {
            self::theme();
        }

        if (empty(self::$config['router']['autoroute_path']))
        {
            self::$config['router']['autoroute_path'] = self::uri()->all();
        }

        self::load('router', [self::$config['router']]);
        if (!empty(self::$config['router']['autoroute']))
        {
            self::router()->autoroute();
        }

        if (!stristr(PHP_SAPI, 'cli'))
        {
            return self::theme()->render();
        }
    }

    /**
     * Load class to the CMS registry
     * extends autoload with the searching for classes within CMS hierarchy
     *
     * @param string $module
     * @param array  $args
     *
     * @return \ReflectionClass
     * @throws \ReflectionException
     */
    protected static function load(string $module, array $args = [])
    {
        $try = [
            'alexandria\\cms\\' . $module,
            'alexandria\\lib\\' . $module,
        ];

        foreach ($try as $class)
        {
            if (class_exists($class))
            {
                $reflect              = new \ReflectionClass($class);
                self::$cache[$module] = $reflect->newInstanceArgs($args);
                return self::$cache[$module];
            }
        }

        throw new \RuntimeException("Can not find module {$module}.");
    }

    public static function registry(string $module, array $args = [])
    {
        if (empty($args) && !empty(self::$config[$module]))
        {
            $args = [self::$config[$module]];
        }

        $module = empty(self::$cache[$module])
            ? self::load($module, $args)
            : self::$cache[$module];

        return $module;
    }

    /**
     * CMS classes registry load/call initiator
     *
     * @param string $module
     * @param array  $args
     *
     * @return mixed
     * @throws \ReflectionException
     */
    public static function __callStatic(string $module, array $args = [])
    {
        return self::registry($module, $args);
    }
}
