<?php

namespace Lattice;

use ReflectionClass;
use ReflectionFunction;
use ReflectionUnionType;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;

trait Dependency_Injection {

    protected static function inject (mixed $call, array $args = [], array $values = []) {

        if (!is_callable($call)) return;

        $args = static::get_inject_args($call, $args, $values);
        return call_user_func_array($call, $args);

    }

    protected static function get_inject_args (mixed $call, array $args = [], array $values = []) {

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

    protected static function function_inject (ReflectionFunctionAbstract $reflection_function, array $args = [], array $values = []) {

        $params = $reflection_function->getParameters();

        foreach ($params as $i => $param) {

            if (!$type = $param->getType())                                  continue;
            if ($type instanceof ReflectionUnionType)                        $type = $type->getTypes()[0];

            // A builtin, `self` or `parent` can never be a class, and class_exists() would send the name through every
            // autoloader; an intersection has no name at all. A real class name is still autoloaded, on purpose.
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) continue;

            $class = $type->getName();

            if (in_array(strtolower($class), ['self', 'parent'], true)) continue;
            if (!class_exists($class))                                  continue;
            if (!method_exists($class, 'get_instance'))                 continue;

            $args[$i] = isset($values[$class]) ?  $values[$class] : call_user_func([$class, 'get_instance'], $args[$i] ?? null);

        }

        return $args;

    }

    protected static function method_inject (ReflectionMethod $reflection_method, array $args = [], array $values = []) {

        return static::function_inject($reflection_method, $args, $values);

    }
    
    protected static function constructor_inject (object|string $class, array $args = [], array $values = []) {

        $reflection  = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        
        if ($constructor) $args = static::method_inject($constructor, $args, $values);

        return $reflection->newInstanceArgs($args);

    }

    protected static function array_inject (array &$array, array $defaults = []) {
    
        if ($array) foreach ($array as $key => &$value) {
        
            if ($defaults && !isset($defaults[$key])) continue;

            $class = $defaults ? $defaults[$key] : $value;

            static::value_inject($class, $value);
        
        }

        return $array;
    
    }

    protected static function value_inject (mixed $class, mixed &$value) {
    
        if (!is_string($class))                     return;
        if (!class_exists($class))                  return;
        if ($value instanceof $class)               return;
        if (!method_exists($class, 'get_instance')) return;

        if ($class === $value) $value = null;

        $value = call_user_func([$class, 'get_instance'], $value);

        return $value;
    
    }

}