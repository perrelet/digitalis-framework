<?php

namespace Digitalis;

class Creational extends Design_Pattern {

    public static function get_instance () {}

    public static function inst () {
    
        return call_user_func_array([static::class, 'get_instance'], func_get_args());
    
    }

    protected function fill ($vars) {

        if ($vars) foreach ($vars as $key => $value) $this->$key = $value;

    }

}