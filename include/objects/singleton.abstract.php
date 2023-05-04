<?php

namespace Digitalis;

abstract class Singleton {

    private static $instances = [];

    protected function __construct() { }
    protected function __clone() { }

    public function __wakeup(){ throw new \Exception("You may not dream me into existence."); }

    public static function get_class_name () {

        return apply_filters('Digitalis/Class/' . static::class, static::class);

    }

    public static function get_instance () {

        $class_name = static::get_class_name();

        if (!isset(self::$instances[$class_name])) {
            self::$instances[$class_name] = new static();
            //self::$instances[$class_name]->run();
            self::$instances[$class_name]->init();
        }

        return self::$instances[$class_name];
        
    }

    //public function run () {} // Depreciated
    public function init () {} // Override me :)

}