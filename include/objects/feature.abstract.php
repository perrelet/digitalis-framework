<?php

namespace Digitalis;

abstract class Feature extends Factory {

    public static function load () {
    
        return call_user_func_array([static::class, 'get_instance'], func_get_args());
    
    }

    public function __construct ($fill = []) {

        $this->fill($fill);

        $this->run();

    }

    public function run () {
    
        
    
    }

    

}