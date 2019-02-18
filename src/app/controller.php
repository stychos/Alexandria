<?php

namespace alexandria\app;

use alexandria\lib\http;
use alexandria\lib\request;
use alexandria\lib\response;
use alexandria\lib\router;
use alexandria\lib\uri;

abstract class controller
{
    /** @var uri */
    protected $uri;

    /** @var http */
    protected $http;

    /** @var request */
    protected $request;

    /** @var response */
    protected $response;

    /** @var router */
    protected $router;

    /** @var config */
    protected $config;

    /** @var theme */
    protected $theme;

    /**
     * Basic initializer, loads common modules and finds method to call
     * If no method can be assotiated then calls main()
     * If no main() method exists then tells router to continue search
     */
    public function __construct()
    {
        $this->uri      = $this->load('uri');
        $this->http     = $this->load('http');
        $this->request  = $this->load('request');
        $this->response = $this->load('response');
        $this->router   = $this->load('router');
        $this->config   = $this->load('config');
        $this->theme    = $this->load('theme');
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
            $this->response->append($this->$action($arg));
        }
        elseif (empty($action) && method_exists($this, 'index'))
        {
            $this->response->append($this->index());
        }
        elseif (method_exists($this, 'main'))
        {
            $this->response->append($this->main($action));
        }
    }

    /*
     * Placeholder for the __bootstrap()
     */
    protected function __bootstrap()
    {

    }

    /**
     * Performs output of the passed $data as JSON and immediately terminates application
     *
     * @param     $data
     * @param int $format JSON formatiing options
     */
    protected function ajax($data, int $code = 200, int $format = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
    {
        $data['success'] = empty($data['error']);
        $this->response->code($code);
        $buffer = $this->response->json($data, $format);
        die($buffer);
    }

    /**
     * Loads framework/application module and returns its instance
     *
     * @param string $module
     * @return mixed
     */
    protected function load(string $module)
    {
        $module = str_replace('/', '\\', $module);
        return kernel::load($module);
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
