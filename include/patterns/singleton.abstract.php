<?php

namespace Digitalis;

use Exception;

abstract class Singleton extends Creational {

    private static $instances = [];

    protected function __construct() {}
    public function __clone()        { throw new Exception("One for all and all for one."); }
    public function __wakeup()       { throw new Exception("You may not dream me into existence."); }

    public static function get_instance (...$args) {

        $class_name = Call::get_class_name(static::class);

        if (!isset(self::$instances[$class_name])) {

            self::$instances[$class_name] = new $class_name();
            self::$instances[$class_name]->init();

        }

        return self::$instances[$class_name];
        
    }

    public function init () {} // Override me :)

}