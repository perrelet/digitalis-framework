<?php

namespace Digitalis;

use Exception;

abstract class Singleton extends Creational {

    use Auto_Instantiate;

    private static $instances = [];

    protected function __construct() {}
    public function __clone()        { throw new Exception("One for all and all for one."); }
    public function __wakeup()       { throw new Exception("You may not dream me into existence."); }

    protected static function construct_instance ($instance) {
    
        parent::construct_instance($instance);

        $instance->init();
    
    }

    public static function get_instance () {

        $class_name = Call::get_class_name(static::class);
        
        if ($class_name != static::class)         return $class_name::get_instance();
        if (!$class_name::instance_condition())   return null;
        if (isset(self::$instances[$class_name])) return self::$instances[$class_name];

        $instance = new $class_name();
        self::$instances[$class_name] = $instance;
        static::construct_instance($instance);

        return $instance;

    }

    public function init () {}

}