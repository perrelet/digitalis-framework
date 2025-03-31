<?php

namespace Digitalis;

class Creational extends Design_Pattern {

    protected static $instance_indexes = [];

    public static function static_init () {}

    public static function instance_condition () {
    
        return true;
    
    }

    public static function get_instance_indexes () {
    
        return self::$instance_indexes;
    
    }

    public function get_instance_count ($class = null) {

        if (is_null($class)) $class = static::class;
    
        return self::$instance_indexes[$class] ?? 0;
    
    }

    public static function is_instantiated () {

        return isset(self::$instance_indexes[static::class]);
    
    }

    protected static function construct_instance ($instance) {

        $class = $instance::class;

        while ($class && property_exists($class, 'instance_indexes')) {
            
            if (isset(self::$instance_indexes[$class])) {
                self::$instance_indexes[$class]++;
            } else {
                self::$instance_indexes[$class] = 1;
            }

            $class = get_parent_class($class);

        }
    
        $instance->instance_index = self::$instance_indexes[$instance::class] ?? null;
    
    }

    public static function make () {}
    public static function get_instance () {}

    public static function inst () {
    
        return call_user_func_array([static::class, 'get_instance'], func_get_args());
    
    }

    protected $instance_index = null; // Not available until after __construct

    public function get_instance_index () {
    
        return $this->instance_index;
    
    }

    public function is_first_instance () {

        if (is_null($this->instance_index)) {

            // We are in the __constructor() & $instance_index hasn't been set by Creational::construct_instance() yet.
            return !$this::get_instance_count();

        } else {

            return ($this->instance_index == 1);

        }
    
    }

}