<?php

namespace Digitalis;

use ReflectionClass;

abstract class Factory extends Creational {

    use Auto_Instantiate, Dependency_Injection;

    protected static $instances = [];
    protected static $cache_group = '__global__';
    protected static $cache_property = null;

    public static function get_instance_map () {

        $map = [];
        foreach (self::$instances as $group => $instances) {
            $map[$group] = [];
            foreach ($instances as $key => $instance) {
                $map[$group][$key] = $instance::class;
            }
        }
    
        return $map;
    
    }

    public static function instance_condition ($data = []) {
    
        return parent::instance_condition();
    
    }

    protected static function construct_instance ($instance, $data = []) {
    
        parent::construct_instance($instance);

        if ($data && is_array($data)) foreach ($data as $prop => $value) {

            if (property_exists($instance, $prop)) $instance->$prop = $value;

        }
    
    }

    public static function create ($data = []) {

        if (is_scalar($data)) return;
        if (!$instance = static::constructor_inject(static::class, array_slice(func_get_args(), 2))) return;

        static::prepare_data($data);
        static::construct_instance($instance, $data);

        return $instance;
    
    }

    public static function get_instance ($data = null) {

        $class_name = Call::get_class_name(static::class, $data);
        if ($class_name != static::class) return $class_name::get_instance($data);

        static::prepare_data($data);
        if (!static::instance_condition($data)) return null;

        $group = static::$cache_group;
        $key   = is_null($data) ? static::class : (is_scalar($data) ? (string) $data : null);

        self::$instances[$group] ??= [];

        if (!is_null($key) && isset(self::$instances[$group][$key])) return self::$instances[$group][$key];

        if ($instance = static::create($data)) {

            if ($cache_key = $instance->get_cache_key()) self::$instances[$group][$cache_key] = $instance;
            if (!is_null($key) && ($key !== $cache_key)) self::$instances[$group][$key]       = $instance;

        }

        return $instance;

    }

    public static function prepare_data (&$data) {}

    protected function get_cache_key () {

        $property = static::$cache_property;

        return $property ? $this->$property : null;
    
    }

}
