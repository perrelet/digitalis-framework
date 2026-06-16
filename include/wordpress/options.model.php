<?php

namespace Digitalis;

// Has_ACF_Fields so the instance can act as the ACF parent for ACF_Row subclasses on an options-page repeater.

class Options extends Model {

    use Has_WP_Hooks;
    use Has_ACF_Fields;

    protected static $prefix     = '';
    protected static $acf_prefix = '';

    // Model Identity

    public static function extract_id ($data = null) {

        return 0;

    }

    public static function validate_id ($id) {

        return $id === 0;

    }

    public function get_wp_meta_type () {

        return 'option';

    }

    public function get_acf_id () {

        return 'option';

    }

    // Option Access

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

    public function get_option ($option, $default = false) {

        return get_option(static::$prefix . $option, $default);

    }

    public function add_option ($option, $value, $autoload = null) {

        return add_option(static::$prefix . $option, $value, '', $autoload);

    }

    public function update_option ($option, $value, $autoload = null) {

        return update_option(static::$prefix . $option, $value, $autoload);

    }

    public function delete_option ($option) {

        return delete_option(static::$prefix . $option);

    }

    // ACF Access (static proxies)

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

    // ACF Access (instance methods)

    public function get_acf_field ($selector, $format_value = true) {

        return get_field(static::$acf_prefix . $selector, 'option', $format_value);

    }

    public function esc_acf_field ($selector, $format_value = true) {

        return trim(esc_attr($this->get_acf_field($selector, $format_value)));

    }

    public function update_acf_field ($selector, $value) {

        return update_field(static::$acf_prefix . $selector, $value, 'option');

    }

    public function update_acf_fields ($data) {

        if ($data) foreach ($data as $selector => $value) $this->update_acf_field($selector, $value);

    }

}
