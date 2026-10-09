<?php

namespace Lattice;

abstract class Plugin_Integration extends Integration {

    protected static $plugin = '';

    public static function instance_condition () {
    
        return Plugins::is_active((string) static::$plugin);
    
    }

}