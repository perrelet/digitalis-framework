<?php

namespace Digitalis;

class Transients extends Utility {

    protected $prefix = '';

    public static function get ($transient) {
    
        return get_transient(static::$prefix . $transient);
    
    }

    public static function set ($transient, $value, $expiration = 0) {
    
        return set_transient(static::$prefix . $transient, $value, $expiration);
    
    }

    public static function delete ($transient) {
    
        return delete_transient(static::$prefix . $transient);
    
    }

}