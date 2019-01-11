<?php

namespace alexandria\cms;

use alexandria\cms;

class controller
{
    protected $uri;
    protected $request;
    protected $http;
    protected $router;
    protected $theme;
    protected $user;

    /**
     * @todo wrap output into the response module here
     */
    public function __construct()
    {
        $this->uri     = cms::module('uri');
        $this->request = cms::module('request');
        $this->router  = cms::module('router');
        $this->theme   = cms::module('theme');
        $this->http    = cms::module('http');

        $class = explode("\\", get_called_class());
        $count = count($class);
        if ($count > 1) {
            $classname = $class[$count - 1];
            if ($classname == 'controller') {
                $classname = $class[$count - 2];
            }
        }
        else {
            $classname = $class[0];
        }

        $action = $this->uri->assoc($classname);
        $arg    = $this->uri->assoc($action);
        if (method_exists($this, $action)) {
            $this->$action($arg);
            $this->router->stop();
        }
        elseif (method_exists($this, 'main')) {
            $this->main();
            $this->router->stop();
        }
        else {
            $this->router->continue();
        }
    }

    protected function ajax_response(
        $data,
        int $format = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) {
        $data['success'] = empty($data['error']);
        echo json_encode($data, $format);
        die();
    }

    public static function __widget()
    {
        return get_called_class()." widget";
    }

    protected function view(string $form, array $args = [])
    {
        return $this->theme->show_form($form, $args);
    }

    protected function load(string $module, ?array $args = [], bool $new_instance = false)
    {
        $module = str_replace('/', '\\', $module);
        return cms::module($module, $args, $new_instance);
    }
}
