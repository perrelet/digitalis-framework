<?php

declare(strict_types=1);

namespace Lattice;

abstract class Service extends Factory {

    public static function get_auto_instantiation () {
        
        return false;
    
    }

}