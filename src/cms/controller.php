<?php

namespace alexandria\cms;

use alexandria\cms;

class controller
{
    public function __construct()
    {
        $class = explode("\\", get_called_class());
        $count = count($class);
        if ($count > 1) {
            $classname = $class[$count - 1];
            if ($classname == 'controller') {
                $classname = $class[$count - 2];
            }
        } else {
            $classname = $class[0];
        }

        $action = cms::module('uri')->assoc($classname);
        $arg = cms::module('uri')->assoc($action);
        if (method_exists($this, $action)) {
            $this->$action($arg);
            cms::module('router')->stop();
        } elseif (method_exists($this, 'main')) {
            $this->main();
            cms::module('router')->stop();
        } else {
            cms::module('router')->continue();
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
        return get_called_class() . " widget";
    }

    protected function view(string $form, array $args = [])
    {
        return cms::module('theme')->show_form($form, $args);
    }

    protected function get(string $module, array $args = [], bool $new_instance = false)
    {
        $module = str_replace('/', '\\', $module);
        return cms::module($module, $args, $new_instance);
    }
}
