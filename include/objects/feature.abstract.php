<?php

namespace Digitalis;

abstract class Feature extends Factory {

    public static function load () {
    
        return call_user_func_array([static::class, 'get_instance'], func_get_args());
    
    }

    public function __construct ($options = []) {

        if ($options) foreach ($options as $key => $value) if (property_exists($this, $key)) $this->$key = $value;

        $this->run();

    }

    public function run () {
    
        
    
    }

    

}