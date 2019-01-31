<?php

namespace alexandria\lib;

/**
 * HTTP Helpers
 */
class http
{
    protected $wroot;

    public function __construct()
    {
        $proto = 'http';
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off'
        || !empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        {
            $proto = 'https';
        }

        $wroot = '';
        if (!empty($_SERVER['HTTP_HOST']))
        {
            $wroot = "{$proto}://{$_SERVER['HTTP_HOST']}";
            $wroot = rtrim($wroot, '/');
        }

        if (empty($_SERVER['PATH_INFO']))
        {
            $_SERVER['PATH_INFO'] = '';
        }

        $sub   = preg_replace("#(/index\.php)?{$_SERVER['PATH_INFO']}#", '', $_SERVER['PHP_SELF']);
        $wroot .= !empty($sub) ? "{$sub}" : '';

        $this->wroot = $wroot;
    }

    /**
     * Redirects to specified URI
     *
     * @param string $to
     */
    public function redirect(string $to)
    {
        $to = str_replace("{root}", $this->wroot, $to);
        header('Location: '.$to);
    }
}
