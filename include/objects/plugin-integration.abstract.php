<?php

namespace Digitalis;

abstract class Plugin_Integration extends Integration {

    protected static $plugin = '';

    public static function instance_condition () {
    
        return is_plugin_active(static::$plugin);
    
    }

}