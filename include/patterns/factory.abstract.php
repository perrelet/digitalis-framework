<?php

namespace Digitalis;

use ReflectionClass;

abstract class Factory extends Creational {

    use Auto_Instantiate, Dependency_Injection;

    protected static $instances = [];
    protected static $cache_property = null;

    public static function instance_condition ($data = []) {
    
        return parent::instance_condition();
    
    }

    protected static function construct_instance ($instance, $data = []) {
    
        parent::construct_instance($instance);

        if ($data && is_array($data)) foreach ($data as $prop => $value) {

            if (property_exists($instance, $prop)) $instance->$prop = $value;

        }
    
    }

    public static function make ($data = [], ...$args) {

        if (!$instance = static::constructor_inject(static::class, array_slice(func_get_args(), 2))) return;

        static::construct_instance($instance, $data);

        return $instance;
    
    }

    public static function get_instance ($data = null) {

        $class_name = Call::get_class_name(static::class);
        if ($class_name != static::class) return $class_name::get_instance($data);

        static::prepare_data($data);

        if (!$class_name::instance_condition($data)) return null;

        if ($data && is_scalar($data)) return self::$instances[$class_name][$data] ?? null;

        $instance = static::make($data);

        if ($key = $instance->get_cache_key()) {

            if (!isset(self::$instances[$class_name])) self::$instances[$class_name] = [];
            self::$instances[$class_name][$key] = $instance;

        }

        return $instance;

    }

    public static function prepare_data (&$data) {}

    protected function get_cache_key () {

        $property = static::$cache_property;

        return $property ? $this->$property : null;
    
    }

}
