<?php

namespace Digitalis;

abstract class Integration extends Singleton {

    protected static function construct_instance ($instance) {
    
        parent::construct_instance($instance);

        $instance->run();
    
    }
    
    public function run () {}

}