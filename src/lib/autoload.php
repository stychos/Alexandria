<?php

namespace alexandria\lib;

class autoload
{
    protected $paths = [];
    protected $registered = false;
    protected $ignore_case = true;


    /**
     * Constructor can take initial namespace to register
     *
     * @param string $namespace
     * @param string $path
     */
    public function __construct(string $namespace = '', string $path = null)
    {
        if (!$path)
        {
            $path = dirname($_SERVER['SCRIPT_FILENAME']);
        }

        $this->register($namespace, $path);
    }

    /**
     * Ensure to cleanup autoloader on destruction
     */
    public function __destruct()
    {
        foreach ($this->paths as $namespace => $path)
        {
            $this->unregister($namespace);
        }
    }

    /**
     * Register path to namespace
     * To the one namespace can be registered multiple paths
     *
     * @param string $namespace Namespace to be used
     * @param string $path      Path to try
     *
     * @return bool
     */
    public function register(string $namespace = '', string $path = './'): bool
    {
        if (!file_exists($path))
        {
            $path = __DIR__.'/../../'.$path;
            if (!file_exists($path))
            {
                throw new \InvalidArgumentException("Autoload path not exist: '{$path}'");
            }
        }

        $namespace = strtolower($namespace);
        $path      = rtrim($path, '\\/');

        if (empty($this->paths[$namespace]))
        {
            $this->paths[$namespace] = [];
        }

        if (!in_array($path, $this->paths[$namespace]))
        {
            $this->paths[$namespace][] = $path;
        }

        if (!$this->registered)
        {
            $this->registered = spl_autoload_register([$this, 'load']);
        }

        return $this->registered;
    }

    /**
     * Unregister path from namespace
     * Also, unregister autoloader when last path is unregistered
     *
     * @param string $namespace Namespace to be used
     * @param string $path      Path to remove (or remove all paths if empty)
     */
    public function unregister(string $namespace = '', string $path = '')
    {
        if (empty($this->paths[$namespace]))
        {
            throw new \InvalidArgumentException("Autoloader namespace is not registered: {$namespace}");
        }

        if (empty($path))
        {
            unset($this->paths[$namespace]);
        }
        else
        {
            foreach ($this->paths[$namespace] as $i => $p)
            {
                if ($p == $path)
                {
                    unset($this->paths[$namespace][$i]);
                }
            }

            if (empty($this->paths[$namespace]))
            {
                unset($this->paths[$namespace]);
            }
        }

        if (empty($this->paths) && $this->registered)
        {
            $this->registered = !spl_autoload_unregister([$this, 'load']);
        }
    }

    /**
     * Class loader
     *
     * @param string $class Class name to load
     *
     * @return bool
     */
    protected function load(string $class): bool
    {
        $namespace = null;
        foreach ($this->paths as $ns => $path)
        {
            if (!empty($ns) && stripos($class."\\", $ns."\\") !== false)
            {
                if ($class !== $ns)
                {
                    $class = trim(str_ireplace($ns, '', $class), '\\');
                }

                $namespace = $ns;
                break;
            }
        }

        // build search_single array from namespace array
        $paths = [];
        if (!is_null($namespace))
        {
            $paths = $this->paths[$namespace];
        }
        elseif (isset($this->paths['']))
        {
            $paths = $this->paths[''];
        }

        $search   = [];
        $filename = str_replace('\\', '/', $class);
        $basename = basename($filename);

        foreach ($paths as $path)
        {
            $simplepath = "{$path}/{$filename}.php";
            $subpath    = "{$path}/{$filename}/{$basename}.php";

            $search [] = $simplepath;
            if ($class !== '')
            {
                $search [] = $subpath;
            }

            if (
                $this->ignore_case && strtolower($simplepath) !== $simplepath
            )
            {
                $search [] = strtolower($simplepath);
                if ($class !== '')
                {
                    $search [] = strtolower($subpath);
                }
            }
        }

        foreach ($search as $file)
        {
            if (file_exists($file))
            {
                require_once($file);
                return true;
            }
        }

        return false;
    }

    /**
     * Modifies search_single to also include lowercased paths
     *
     * @param bool $ignore_case
     */
    public function ignore_case(bool $ignore_case)
    {
        $this->ignore_case = $ignore_case;
    }
}

return new autoload();
