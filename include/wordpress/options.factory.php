<?php

namespace Digitalis;

class Options extends Factory {

    use Has_WP_Hooks;

    protected static $cache_group    = self::class;
    protected static $cache_property = 'prefix';

    protected $prefix     = '';
    protected $acf_prefix = '';

    public static function get ($option, $default = false) {

        return static::get_instance()->get_option($option, $default);

    }

    public static function add ($option, $value, $autoload = null) {

        return static::get_instance()->add_option($option, $value, $autoload);
    
    }

    public static function update ($option, $value, $autoload = null) {
    
        return static::get_instance()->update_option($option, $value, $autoload);
    
    }

    public static function delete ($option) {

        return static::get_instance()->delete_option($option);
    
    }

    //

    public function get_option ($option, $default = false) {

        return get_option($this->prefix . $option, $default);

    }

    public function add_option ($option, $value, $autoload = null) {
    
        return add_option($this->prefix . $option, $value, '', $autoload);
    
    }

    public function update_option ($option, $value, $autoload = null) {
    
        return update_option($this->prefix . $option, $value, $autoload);
    
    }

    public function delete_option ($option) {
    
        return delete_option($this->prefix . $option);
    
    }

    // ACF

    public static function get_field ($selector, $format_value = true) {

        return static::get_instance()->get_acf_field($selector, $format_value);

    }

    public static function esc_field ($selector, $format_value = true) {

        return static::get_instance()->esc_acf_field($selector, $format_value);

    }

    public static function update_field ($selector, $value) {

        return static::get_instance()->update_acf_field($selector, $value);

    }

    public static function update_fields ($data) {

        return static::get_instance()->update_acf_fields($data);
    
    }

    //

    public function get_acf_field ($selector, $format_value = true) {

        return get_field($this->acf_prefix . $selector, 'option', $format_value);

    }

    public function esc_acf_field ($selector, $format_value = true) {

        return trim(esc_attr($this->get_acf_field($selector, $format_value)));

    }

    public function update_acf_field ($selector, $value) {

        return update_field($this->acf_prefix . $selector, $value, 'option');
        
    }

    public function update_acf_fields ($data) {
    
        if ($data) foreach ($data as $selector => $value) $this->update_acf_field($selector, $value);
    
    }

}