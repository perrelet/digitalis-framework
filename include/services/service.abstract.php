<?php

declare(strict_types=1);

namespace Lattice;

abstract class Service extends Factory {

    const INSTANCE_KIND = 'service';

    public static function get_auto_instantiation () {
        
        return false;
    
    }

}