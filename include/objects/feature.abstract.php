<?php

namespace Digitalis;

abstract class Feature extends Factory {

    use Has_WP_Hooks;

    protected static $cache_group    = self::class;
    protected static $cache_property = 'id';

    protected $id = null;

    public static function load () {

        return call_user_func_array([static::class, 'get_instance'], func_get_args());

    }

    protected static function construct_instance ($instance, $data = []) {

        parent::construct_instance($instance, $data);

        $instance->add_hooks((array) $instance->get_hooks());
        $instance->run();

    }

    // Unless a subclass sets its own $cache_group, each feature class gets a group of its own.
    protected static function get_cache_group () {

        return (static::$cache_group === self::class) ? static::class : static::$cache_group;

    }

    protected static function get_data_key ($data) {

        if ($data === []) $data = null;

        if (!is_array($data)) return parent::get_data_key($data);

        return static::$cache_property ? ($data[static::$cache_property] ?? null) : null;

    }

    public function run () {}

    public function get_hooks () {

        return [];

    }

}