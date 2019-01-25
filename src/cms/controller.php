<?php

namespace alexandria\cms;

use alexandria\cms;

/**
 * Class controller
 *
 * Common controller template
 *
 * @package alexandria\cms
 */
class controller
{
    protected $uri;
    protected $http;
    protected $request;
    protected $router;
    protected $config;
    protected $theme;

    /**
     * Basic initializer, loads common modules and finds method to call
     * If no method can be assotiated then calls main()
     * If no main() method exists then tells router to continue search
     *
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->uri     = $this->module('uri');
        $this->http    = $this->module('http');
        $this->request = $this->module('request');
        $this->router  = $this->module('router');
        $this->config  = $this->module('config');
        $this->theme   = $this->module('theme');
        $this->__bootstrap();

        $class = explode("\\", get_called_class());
        $count = count($class);
        if ($count > 1)
        {
            $classname = $class[$count - 1];
            if ($classname == 'controller')
            {
                $classname = $class[$count - 2];
            }
        }
        else
        {
            $classname = $class[0];
        }

        $action = $this->uri->assoc($classname);
        $arg    = $this->uri->assoc($action);
        if (method_exists($this, $action))
        {
            echo $this->$action($arg);
            $this->router->stop();
        }
        elseif (empty($action) && method_exists($this, 'index'))
        {
            $this->router->stop();
            echo $this->index();
        }
        elseif (method_exists($this, 'main'))
        {
            $this->router->stop(); // stop before controller call 'cause controller may want to return router flow
            echo $this->main($action);
        }
        else
        {
            $this->router->continue();
        }
    }

    /*
     * Placeholder for the __bootstrap()
     */
    protected function __bootstrap()
    {

    }

    /**
     * Placeholder for the widgets
     *
     * @return string
     */
    public static function __widget()
    {
        return get_called_class();
    }

    /**
     * Performs output of the passed $data as JSON and immediately terminates application
     *
     * @param     $data
     * @param int $format JSON formatiing options
     */
    protected function ajax($data, int $format = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    {
        $data['success'] = empty($data['error']);
        echo json_encode($data, $format);
        die();
    }

    /**
     * Loads framework/application module and returns its instance
     *
     * @param string     $module
     * @param array|null $args
     * @param bool       $new_instance
     * @return mixed
     * @throws \ReflectionException
     */
    protected function module(string $module, ?array $args = [], bool $new_instance = false)
    {
        $module = str_replace('/', '\\', $module);
        return cms::module($module, $args, $new_instance);
    }

    /**
     * Renders view via theme module
     *
     * @param string $form
     * @param array  $args
     * @return mixed
     */
    protected function view(string $form, array $args = [])
    {
        return $this->theme->view($form, $args);
    }
}
