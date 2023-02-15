<?php

namespace Digitalis;

abstract class Singleton {

    private static $instances = [];

    protected function __construct() { }
    protected function __clone() { }

    public function __wakeup(){ throw new \Exception("You may not dream me into existence."); }

    public static function get_instance () {

        $class = static::class;

        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
            self::$instances[$class]->run();
        }

        return self::$instances[$class];
        
    }

    public function run () {} // Override me :)

}