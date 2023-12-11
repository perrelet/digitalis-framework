<?php

namespace Digitalis;

use ReflectionClass;

abstract class Factory extends Creational {

    use Dependency_Injection;

    public static function get_instance () {
    
        $class_name = Call::get_class_name(static::class);

        return static::constructor_inject($class_name, func_get_args()); 
    
    }

}
