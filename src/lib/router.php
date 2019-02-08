<?php

namespace alexandria\lib;

class router
{
    protected $autoroute_path;
    protected $default_route;
    protected $fail_route;

    protected $pre_routes;
    protected $post_routes;
    protected $rewrites;

    protected $search_controllers;
    protected $continue;
    protected $tail;

    public function __construct(array $args)
    {
        $this->search_controllers = $args['search_controllers'] ?? ['{$route}\\controller', '{$route}'];

        $this->autoroute_path = $args['autoroute_path'] ?? $_SERVER['PATH_INFO'] ?? '';
        $this->autoroute_path = urldecode($this->autoroute_path);

        $this->default_route = $args['default_route'] ?? 'index';
        $this->fail_route    = $args['fail_route'] ?? 'notfound';

        $this->pre_routes  = $args['pre_routes'] ?? [];
        $this->post_routes = $args['post_routes'] ?? [];
        $this->rewrites    = $args['rewrites'] ?? [];
    }

    /**
     * @param string $route
     *
     * @return array returns [ 'success' => bool, 'output' => ?string ] array
     * @todo establish router-controller relations for main() and index()
     */
    protected function _route(string $route): array
    {
        $_route = $route;
        $ret    = [
            'success' => false,
            'output'  => null,
        ];

        foreach ($this->rewrites as $from => $to)
        {
            $_route = str_replace($from, $to, $_route);
        }

        foreach ($this->search_controllers as $class)
        {
            $controller = str_replace('{$route}', $_route, $class);
            $controller = str_replace('/', '\\', $controller);
            $controller = preg_replace('~\\\+~', '\\', $controller);

            if (class_exists($controller))
            {
                $this->tail = str_replace($_route, '', $this->autoroute_path);
                $this->tail = trim(rtrim($this->tail, '/'), '/');

                ob_start();
                new $controller();
                $ret['output']  = ob_get_clean();
                $ret['success'] = true;
                break;
            }
        }

        return $ret;
    }

    /**
     * @param bool $use_fallback
     *
     * @return string|null
     */
    public function autoroute(bool $redirect = false): ?string
    {
        $buff           = null; // combined output buffer
        $this->continue = true; // run pre-routes first, so set this flag to true temporarily

        if (!$redirect) // do prerouting only on independent calls
        {
            $buff .= $this->preroute();
            if (!$this->continue)
            {
                return $buff; // some preroute controller told us to halt
            }
        }

        $routed         = false;
        $this->continue = false; // prerouting finished, not disabling autocontinuation
        if (!empty($this->autoroute_path))
        {
            $path = explode('/', $this->autoroute_path);
            do
            {
                $sub = implode('/', $path);
                $res = $this->_route($sub);
                if ($res['success'])
                {
                    $routed = true;
                    $buff   .= $res['output'];

                    if ($this->continue) // called controller had set us to continue autoroute cycle
                    {
                        $this->continue = false;
                    }
                    else // controller doesn't stated anything, halt cycle after first matched controller call
                    {
                        break;
                    }
                }

                array_pop($path); // walk up to the root
            }
            while (!empty($path));
        }

        if ($redirect) // that was redirect cycle, return result
        {
            return $buff;
        }

        // no route was found and route string became empty, trying default route
        if (!$routed && empty($this->autoroute_path))
        {
            $res    = $this->_route($this->default_route);
            $routed = $res['success'];
            $buff   .= $res['output'] ?? null;
        }

        // no default route succeeded, trying fail route
        if (!$routed)
        {
            $res    = $this->_route($this->fail_route);
            $routed = $res['success'];
            $buff   .= $res['output'] ?? null;
        }

        // do postrouting after all
        $buff .= $this->postroute();

        // no routes called, trigger error
        if (!$routed)
        {
            if (stripos('CLI', PHP_SAPI) === false)
            {
                http_response_code(404);
            }

            trigger_error("Route class for {$this->autoroute_path} not found. Default and fail routes are not found too. Check your configuration.", E_USER_WARNING);
        }

        return $buff;
    }

    protected function preroute(): ?string
    {
        $buff = null;

        foreach ($this->pre_routes as $route)
        {
            if ($this->continue)
            {
                $res  = $this->_route($route);
                $buff .= $res['output'] ?? null;
            }
        }

        return $buff;
    }

    protected function postroute(): ?string
    {
        $buff = null;

        foreach ($this->post_routes as $route)
        {
            $res  = $this->_route($route);
            $buff .= $res['output'] ?? null;
        }

        return $buff;
    }

    /**
     * @param string   $to
     * @param uri|null $uri
     *
     * @return string|null
     */
    public function redirect(string $to, uri $uri = null): ?string
    {
        $this->autoroute_path = $to;

        if ($uri)
        {
            $rewrite = rtrim($to, '/').'/'.$this->tail;
            $uri->build($rewrite);
        }

        $buff = $this->autoroute($use_fallbacks = false);
        return $buff;
    }

    public function continue()
    {
        $this->continue = true;
    }

    public function stop()
    {
        $this->continue = false;
    }

    public function tail()
    {
        return $this->tail;
    }
}
