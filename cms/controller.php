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
            $classname = $class[$count - 2];
        } else {
            $classname = $class[0];
        }

        $action = cms::uri()->assoc($classname);
        $arg = cms::uri()->assoc($action);
        if (method_exists($this, $action)) {
            $this->$action($arg);
        } else {
            cms::router()->continue();
        }
    }

    public static function __widget()
    {
        return get_called_class() . " widget";
    }
}
