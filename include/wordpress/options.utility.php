<?php

namespace Digitalis;

class Options extends Utility {

    protected static $prefix     = '';
    protected static $acf_prefix = '';

    public static function get ($option, $default = false) {

        return get_option(static::$prefix . $option, $default);

    }

    public static function add ($option, $value, $autoload = null) {
    
        return add_option(static::$prefix . $option, $value, '', $autoload);
    
    }

    public static function update ($option, $value, $autoload = null) {
    
        return update_option(static::$prefix . $option, $value, $autoload);
    
    }

    public static function delete ($option) {
    
        return delete_option(static::$prefix . $option);
    
    }

    // ACF

    public static function get_field ($selector, $format_value = true) {

        return get_field(static::$acf_prefix . $selector, 'option', $format_value);

    }

    public static function esc_field ($selector, $format_value = true) {

        return trim(esc_attr(static::get_field($selector, $format_value)));

    }

    public static function update_field ($selector, $value) {

        return update_field(static::$acf_prefix . $selector, $value, 'option');
        
    }

    public static function update_fields ($data) {
    
        if ($data) foreach ($data as $selector => $value) static::update_field($selector, $value);
    
    }

}