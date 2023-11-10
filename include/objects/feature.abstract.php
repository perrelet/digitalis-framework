<?php

namespace Digitalis;

abstract class Feature {

    public static function load ($options = []) {
    
        return new static($options);
    
    }

    public static function get_instance ($options = []) {
    
        return static::load($options);
    
    }

    public function __construct ($options = []) {

        if ($options) foreach ($options as $key => $value) if (property_exists($this, $key)) $this->$key = $value;

        $this->run();

    }

    public function run () {
    
        
    
    }

    

}