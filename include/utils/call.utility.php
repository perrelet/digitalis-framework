<?php

namespace Digitalis;

class Call extends Utility {

    public static function get_class_name ($class_name) {
    
        return apply_filters('Digitalis/Class/' . str_replace('\\', '/', ltrim($class_name, '\\')), $class_name);
    
    }

    public static function static ($class_name, $method, ...$args) {
    
        return static::static_array($class_name, $method, $args);
    
    }

    public static function static_array ($class_name, $method, $args = []) { // Can pass by args ref
    
        $class_name = static::get_class_name($class_name);

        return call_user_func_array([$class_name, $method], $args);
    
    }

}