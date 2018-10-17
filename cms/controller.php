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

        $action = cms::uri()->assoc($classname);
        $arg = cms::uri()->assoc($action);
        if (method_exists($this, $action)) {
            $this->$action($arg);
        } elseif (method_exists($this, 'main')) {
            $this->main();
        } else {
            cms::router()->continue();
        }
    }

    protected function ajax_response($data)
    {
        $data['success'] = empty($data['error']);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        die();
    }

    public static function __widget()
    {
        return get_called_class() . " widget";
    }
}
