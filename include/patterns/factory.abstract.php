<?php

namespace Digitalis;

use ReflectionClass;

abstract class Factory extends Creational {

    use Dependency_Injection;

    public static function get_class_name () {

        return apply_filters('Digitalis/Class/' . str_replace('\\', '/', ltrim(static::class, '\\')), static::class);

    }

    public static function get_instance () {
    
        $class_name = static::get_class_name();

        return static::constructor_inject($class_name, func_get_args()); 
    
    }

}
