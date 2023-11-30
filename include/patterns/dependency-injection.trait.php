<?php

namespace Digitalis;

use ReflectionClass;
use ReflectionFunction;

trait Dependency_Injection {

    protected static function inject ($call, $args = [], $values = []) {
    
        if (is_array($call)) {

            if (!is_callable($call)) return;

            $class = new ReflectionClass($call[0]);
            $func  = $class->getMethod($call[1]);
            $args = static::method_inject($func, $args, $values);

        } else {

            $func  = new ReflectionFunction($call);
            $args  = static::function_inject($func, $args, $values);

        }

        return call_user_func_array($call, $args);
    
    }

    protected static function function_inject ($reflection_function, $args = [], $values = []) {

        $params = $reflection_function->getParameters();

        if ($params) foreach ($params as $i => $param) {

            if (!$type = $param->getType())             continue;
            if (!$class = $type->getName())             continue;
            if (!class_exists($class))                  continue; 
            if (!method_exists($class, 'get_instance')) continue;

            $args[$i] = isset($values[$class]) ?  $values[$class] : call_user_func([$class, 'get_instance']);

        }

        return $args;

    }

    protected static function method_inject ($reflection_method, $args = [], $values = []) {

        return static::function_inject($reflection_method, $args, $values);

    }
    
    protected static function constructor_inject ($class, $args = [], $values = []) {

        $reflection  = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        $args        = static::method_inject($constructor, $args, $values);

        return $reflection->newInstanceArgs($args);

    }

    protected static function array_inject (&$array, $defaults = []) {
    
        if ($array) foreach ($array as $key => &$value) {
        
            if ($defaults && !isset($defaults[$key])) continue;

            $class = $defaults ? $defaults[$key] : $value;

            static::value_inject($class, $value);
        
        }

        return $array;
    
    }

    protected static function value_inject ($class, &$value) {
    
        if (is_array($class))                       return;
        if (!class_exists($class))                  return;
        if ($value instanceof $class)               return;
        if (!method_exists($class, 'get_instance')) return;

        $value = call_user_func([$class, 'get_instance'], $value);

        return $value;
    
    }

}