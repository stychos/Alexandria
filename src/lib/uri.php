<?php

namespace alexandria\lib;

class uri
{
    protected $uri;
    protected $raw_uri;
    protected $uri_array;
    protected $raw_uri_array;
    protected $aliases = [];

    protected $docroot;
    protected $raw_docroot;
    protected $wwwroot;
    protected $raw_wwwroot;
    protected $subdir;

    public function __construct($config = [])
    {
        $from = $config->from ?? null;
        $vars = $config->vars ?? [];
        $this->build($from, $vars);
    }

    public function build(string $uri = null, array $cli_vars = [])
    {
        if (stripos(PHP_SAPI, 'CLI') !== false)
        {
            $trace        = debug_backtrace();
            $this->subdir = dirname($_SERVER['PHP_SELF']);
            if ($this->subdir === '.')
            {
                $this->subdir = '';
            }

            $this->docroot     = dirname($trace[count($trace) - 1]['file']);
            $this->raw_docroot = rtrim(str_replace($this->subdir, '', $this->docroot), '/');

            if (!empty($uri))
            {
                $this->uri = $uri;
            }
            else
            {
                $k   = 0;
                $uri = '';
                array_shift($_SERVER['argv']);
                while (isset($_SERVER['argv'][$k]))
                {
                    $element = str_replace('/', '^^S^^', $_SERVER['argv'][$k]);
                    $uri     .= $element . '/';
                    $k++;
                }

                $this->uri = rtrim($uri, '/');
            }

            $this->raw_uri     = "{$this->subdir}/{$this->uri}";
            $this->raw_wwwroot = $cli_vars['raw_wwwroot'] ?? 'http://localhost';
            $this->wwwroot     = $cli_vars['wwwroot'] ?? "{$this->raw_wwwroot}/{$this->subdir}";

            $this->uri_array     = explode('/', $this->uri);
            $this->raw_uri_array = explode('/', $this->raw_uri);
            foreach ($this->uri_array as &$item)
            {
                $item = str_replace('^^S^^', '/', $item);
            }
            unset($item);
            foreach ($this->raw_uri_array as &$item)
            {
                $item = str_replace('^^S^^', '/', $item);
            }
            unset($item);
        }
        else
        {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' || isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') ? 'https://' : 'http://';

            $this->raw_docroot = $_SERVER['DOCUMENT_ROOT'];
            $this->raw_wwwroot = $scheme . $_SERVER['HTTP_HOST'];

            $srv          = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
            $sub          = preg_replace('#/[^/]+$#', '', $srv);
            $this->subdir = trim($sub, '/');

            $this->raw_uri = $uri ?? $_SERVER['REQUEST_URI'];
            $this->raw_uri = trim(rtrim($this->raw_uri, '/'), '/');
            $this->uri     = str_replace("{$this->subdir}", '', $this->raw_uri);
            $this->uri     = trim(rtrim($this->uri, '/'), '/');

            $this->docroot = rtrim("{$this->raw_docroot}/{$this->subdir}", "/");
            $this->wwwroot = rtrim("{$this->raw_wwwroot}/{$this->subdir}", "/");

            $this->uri_array     = explode('/', preg_replace('/\?.+/', '', $this->uri));
            $this->raw_uri_array = explode('/', preg_replace('/\?.+/', '', $this->raw_uri));
        }
    }

    public function index(int $index, bool $raw = false)
    {
        $keys = $raw ? $this->raw_uri_array : $this->uri_array;
        return isset($keys[$index]) ? $keys[$index] : false;
    }

    public function exists(string $key, bool $raw = false)
    {
        $keys = $raw ? $this->raw_uri_array : $this->uri_array;
        foreach ($keys as $i => $k)
        {
            if ($k === $key)
            {
                return true;
            }
        }

        return false;
    }

    public function assoc(string $key = null, bool $raw = false)
    {
        if (empty($key))
        {
            return false;
        }

        $ret  = false;
        $keys = $raw ? $this->raw_uri_array : $this->uri_array;
        foreach ($keys as $i => $k)
        {
            if ($k === $key)
            {
                $ret = isset($keys[$i + 1]) ? $keys[$i + 1] : '';

                break;
            }
        }

        // on cli mode, return only mtched values (skip --args)
        if (
            stripos(PHP_SAPI, 'CLI') !== false && strpos($ret, '--') === 0
        )
        {
            $ret = '';
        }

        return $ret;
    }

    public function all(bool $raw = false): string
    {
        return $raw ? $this->raw_uri : $this->uri;
    }

    public function to(string $path = '/', bool $raw = false): string
    {
        $path = trim(rtrim($path, '/'), '/');
        return $raw ? "{$this->raw_wwwroot}/{$path}" : "{$this->wwwroot}/{$path}";
    }

    public function path(string $path = '/', bool $raw = false): string
    {
        $path = trim(rtrim($path, '/'), '/');
        return $raw ? "{$this->raw_docroot}/{$path}" : "{$this->docroot}/{$path}";
    }

    public function add_aliases(array $aliases = [])
    {
        foreach ($aliases as $from => $to)
        {
            $this->aliases[$from] = $to;
        }
    }

    public function push(string $key)
    {
        $this->uri = "{$key}/{$this->uri}";
        $this->__construct($this->uri);
    }

    public function pop()
    {
        $this->uri = preg_replace('#^[^/]+/?#', '', $this->uri);
        $this->__construct($this->uri);
    }
}
