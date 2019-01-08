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
        $proto = @$_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'off' || @$_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http';
        $wroot = @$_SERVER['HTTP_HOST'] ? $proto.'://'.rtrim($_SERVER['HTTP_HOST'], '/') : '';
        $sub   = preg_replace("#(/index\.php)?{$_SERVER['PATH_INFO']}#", '', $_SERVER['PHP_SELF']);
        $wroot .= !empty($sub) ? "{$sub}" : '';

        $this->wroot = $wroot;
    }

    /**
     * Redirects to specified URI
     */
    public function redirect(string $to)
    {
        $to = str_replace("{root}", $this->wroot, $to);
        header('Location: '.$to);
    }
}
