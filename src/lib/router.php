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
    protected $active_route;
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

    public function route(string $route): bool
    {
        $original_route = $route;
        foreach ($this->rewrites as $from => $to) {
            $route = str_replace($from, $to, $route);
        }

        foreach ((array) $this->search_controllers as $class) {
            $controller = str_replace('{$route}', $route, $class);
            $controller = str_replace('/', '\\', $controller);
            if (class_exists($controller) && method_exists($controller, '__construct')) {
                $this->active_route = $route;
                $this->tail         = str_replace($original_route, '', $this->autoroute_path);
                $this->tail         = trim(rtrim($this->tail, '/'), '/');

                new $controller();
                return true;
            }
        }

        return false;
    }

    /**
     * @param bool $use_fallback
     *
     * @return bool
     * @throws \Exception
     */
    public function autoroute(bool $use_fallback = true)
    {
        // run pre-routes first
        $this->continue = true;
        $this->preroute();

        // some preroute controller told us to halt
        if (!$this->continue) {
            return;
        }

        // do not autocontinue after prerouting
        $this->continue = false;

        // main routing cycle, walking up by query path
        $routed = false;
        if (!empty($this->autoroute_path)) {
            $path = explode('/', $this->autoroute_path);

            do {
                $sub = implode('/', $path);
                if ($this->route($sub)) {
                    $routed = true;

                    // check if called controller has set router to continue auto-routing
                    if ($this->continue) {
                        $routed         = false;
                        $this->continue = false;
                    }
                    else {
                        break;
                    }
                }

                array_pop($path);
            }
            while (!empty($path));
        }

        if (!$use_fallback) {
            return $routed;
        }

        // try default route if no automatic route was found and if path is empty
        if (!$routed && empty($this->autoroute_path)) {
            $routed = $this->route($this->default_route);
        }

        // try fail route if no automatic nor default routes are found
        if (!$routed) {
            $routed = $this->route($this->fail_route);
        }

        // run post-routes after all
        $this->postroute();

        // if we still not routed, then it's a fatal
        if (!$routed) {
            if (stripos('CLI', PHP_SAPI) === false) {
                http_response_code(404);
            }

            throw new \Exception("Route class for {$this->autoroute_path} not found. Default and fail routes are not found too. Check your configuration.");
        }

        return true;
    }

    /**
     * @param string   $to
     * @param uri|null $uri
     *
     * @throws \exception
     */
    public function reroute(string $to, uri $uri = null)
    {
        $this->autoroute_path = $to;
        $this->autoroute($use_fallbacks = false);

        if ($uri) {
            $new_uri = rtrim($to, '/').'/'.$this->tail;
            $uri->build($new_uri);
        }
    }

    public function continue()
    {
        $this->continue = true;
    }

    public function stop()
    {
        $this->continue = false;
    }

    protected function preroute()
    {
        foreach ($this->pre_routes as $route) {
            if ($this->continue) {
                $this->route($route);
            }
        }
    }

    protected function postroute()
    {
        foreach ($this->post_routes as $route) {
            $this->route($route);
        }
    }

    public function tail()
    {
        return $this->tail;
    }
}
