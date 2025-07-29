<?php

namespace Digitalis;

use ReflectionClass;
use ReflectionFunction;
use ReflectionUnionType;

trait Dependency_Injection {

    protected static function inject ($call, $args = [], $values = []) {

        if (!is_callable($call)) return;

        $args = static::get_inject_args($call, $args, $values);
        return call_user_func_array($call, $args);

    }

    protected static function get_inject_args ($call, $args = [], $values = []) {

        if (is_callable($call)) {

            if (is_array($call)) {

                $class = new ReflectionClass($call[0]);
                $func  = $class->getMethod($call[1]);
                $args = static::method_inject($func, $args, $values);

            } else {

                $func  = new ReflectionFunction($call);
                $args  = static::function_inject($func, $args, $values);

            }

        }

        return $args;
    
    }

    protected static function function_inject ($reflection_function, $args = [], $values = []) {

        $params = $reflection_function->getParameters();

        foreach ($params as $i => $param) {

            if (!$type = $param->getType())             continue;
            if ($type instanceof ReflectionUnionType)   $type = $type->getTypes()[0];
            if (!$class = $type->getName())             continue;
            if (!class_exists($class))                  continue;
            if (!method_exists($class, 'get_instance')) continue;

            $args[$i] = isset($values[$class]) ?  $values[$class] : call_user_func([$class, 'get_instance'], $args[$i] ?? null);

        }

        return $args;

    }

    protected static function method_inject ($reflection_method, $args = [], $values = []) {

        return static::function_inject($reflection_method, $args, $values);

    }
    
    protected static function constructor_inject ($class, $args = [], $values = []) {

        $reflection  = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        
        if ($constructor) $args = static::method_inject($constructor, $args, $values);

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
    
        if (!is_string($class))                     return;
        if (!class_exists($class))                  return;
        if ($value instanceof $class)               return;
        if (!method_exists($class, 'get_instance')) return;

        if ($class === $value) $value = null;

        $value = call_user_func([$class, 'get_instance'], $value);

        return $value;
    
    }

}