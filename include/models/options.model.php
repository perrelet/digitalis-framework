<?php

namespace Lattice;

// Has_Fields so the instance can act as the parent for ACF_Row subclasses on an options-page repeater.

class Options extends Model {

    use Has_WP_Hooks;
    use Has_Fields;

    protected static $prefix     = '';
    protected static $acf_prefix = '';

    // Model Identity

    public static function extract_id (mixed $data = null) {

        return 0;

    }

    public static function validate_id (mixed $id) {

        return $id === 0;

    }

    public function get_wp_meta_type () {

        return 'option';

    }

    // Option Access

    public static function get (string $option, mixed $default = false) {

        return static::get_instance()->get_option($option, $default);

    }

    public static function add (string $option, mixed $value, bool|string|null $autoload = null) {

        return static::get_instance()->add_option($option, $value, $autoload);

    }

    public static function update (string $option, mixed $value, bool|string|null $autoload = null) {

        return static::get_instance()->update_option($option, $value, $autoload);

    }

    public static function delete (string $option) {

        return static::get_instance()->delete_option($option);

    }

    public function get_option (string $option, mixed $default = false) {

        return get_option(static::$prefix . $option, $default);

    }

    public function add_option (string $option, mixed $value, bool|string|null $autoload = null) {

        return add_option(static::$prefix . $option, $value, '', $autoload);

    }

    public function update_option (string $option, mixed $value, bool|string|null $autoload = null) {

        return update_option(static::$prefix . $option, $value, $autoload);

    }

    public function delete_option (string $option) {

        return delete_option(static::$prefix . $option);

    }

    // ACF Access (static proxies)

    public static function get_field (string $selector, bool $format_value = true) {

        return static::get_instance()->get_acf_field($selector, $format_value);

    }

    public static function esc_field (string $selector, bool $format_value = true) {

        return static::get_instance()->esc_acf_field($selector, $format_value);

    }

    public static function update_field (int|string $selector, mixed $value) {

        return static::get_instance()->update_acf_field($selector, $value);

    }

    public static function update_fields ($data) {

        return static::get_instance()->update_acf_fields($data);

    }

    // ACF Access (instance methods)

    public function get_acf_field (string $selector, bool $format_value = true) {

        return $this->field_call('get', static::$acf_prefix . $selector, $format_value);

    }

    public function esc_acf_field (string $selector, bool $format_value = true) {

        return trim(esc_attr($this->get_acf_field($selector, $format_value)));

    }

    public function update_acf_field (int|string $selector, mixed $value) {

        return $this->field_call('update', static::$acf_prefix . $selector, $value);

    }

    public function update_acf_fields ($data) {

        if ($data) foreach ($data as $selector => $value) $this->update_acf_field($selector, $value);

    }

}
