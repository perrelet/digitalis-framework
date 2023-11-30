<?php

namespace Digitalis;

use ReflectionClass;

Trait Factory {

    public static function get_class_name () {

        return apply_filters('Digitalis/Class/' . str_replace('\\', '/', ltrim(static::class, '\\')), static::class);

    }

    public static function get_instance (...$args) {
    
        $class_name = static::get_class_name();

        $service_reflection = new ReflectionClass($class_name);
        $constructor_params = $service_reflection->getConstructor()->getParameters();

        if ($constructor_params) foreach ($constructor_params as $i => $param) {

            if (!$type = $param->getType())                 continue;
            if (!$type_name = $type->getName())             continue;
            if (!class_exists($type_name))                  continue; 
            if (!method_exists($type_name, 'get_instance')) continue;

            $args[$i] = call_user_func([$type_name, 'get_instance']);
        
        }

        return $service_reflection->newInstanceArgs($args);
    
    }

    public static function inst (...$args) {
    
        return static::get_instance(...$args);
    
    }

}
