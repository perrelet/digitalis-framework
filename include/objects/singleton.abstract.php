<?php

namespace Digitalis;

use Exception;

abstract class Singleton {

    private static $instances = [];

    protected function __construct() {}
    public function __clone()        { throw new Exception("One for all and all for one."); }
    public function __wakeup()       { throw new Exception("You may not dream me into existence."); }

    public static function get_class_name () {

        return apply_filters('Digitalis/Class/' . str_replace('\\', '/', ltrim(static::class, '\\')), static::class);

    }

    public static function get_instance () {

        $class_name = static::get_class_name();

        if (!isset(self::$instances[$class_name])) {

            self::$instances[$class_name] = new $class_name();
            self::$instances[$class_name]->init();

        }

        return self::$instances[$class_name];
        
    }

    public static function inst () {
        
        return self::get_instance();
        
    }

    public function init () {} // Override me :)

}