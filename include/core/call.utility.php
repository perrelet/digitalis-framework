<?php

namespace Lattice;

class Call extends Utility {

    public static function get_class_name (string $class_name, mixed $data = null) {
    
        return Hook::filter(['lattice', 'class', $class_name], $class_name, $data);
    
    }

    public static function static (string $class_name, string $method, mixed ...$args) {
    
        return static::static_array($class_name, $method, $args);
    
    }

    public static function static_array (string $class_name, string $method, array $args = []) { // Can pass by args ref
    
        $class_name = static::get_class_name($class_name);

        return call_user_func_array([$class_name, $method], $args);
    
    }

}