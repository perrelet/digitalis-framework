<?php

namespace Digitalis;

abstract class Feature extends Factory {

    public static function load () {
    
        return call_user_func_array([static::class, 'get_instance'], func_get_args());
    
    }

    protected static function construct_instance ($instance, $data = []) {
    
        parent::construct_instance($instance, $data);

        $instance->run();
    
    }

    public function run () {}

}