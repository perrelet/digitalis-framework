<?php

namespace Lattice;

class Transients extends Utility {

    protected static $prefix = '';

    public static function get (string $transient) {
    
        return get_transient(static::$prefix . $transient);
    
    }

    public static function set (string $transient, mixed $value, int $expiration = 0) {
    
        return set_transient(static::$prefix . $transient, $value, $expiration);
    
    }

    public static function delete (string $transient) {
    
        return delete_transient(static::$prefix . $transient);
    
    }

}