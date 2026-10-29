<?php

declare(strict_types=1);

namespace Lattice;

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

        if (is_array($data)) return static::$cache_property ? ($data[static::$cache_property] ?? static::get_default_name()) : null;
        if (!is_null($data)) return parent::get_data_key($data);

        return static::get_default_name() ?? static::class;

    }

    // The naming property's declared default, unless another class in the family holds that name.
    protected static function get_default_name () : ?string {

        if (!static::$cache_property) return null;

        $name = (new \ReflectionClass(static::class))->getDefaultProperties()[static::$cache_property] ?? null;

        if (is_null($name)) return null;

        $held = self::$instances[static::get_cache_group()][$name] ?? null;

        if ($held && !($held instanceof static)) {

            if (!isset(self::$instances[static::get_cache_group()][static::class])) _doing_it_wrong(static::class . '::get_instance', esc_html(static::class . " shares the name '{$name}' with " . get_class($held) . ', so each is found by its class instead.'), '');

            return null;

        }

        return (string) $name;

    }

    // A name another class in the family holds stays theirs: this one is kept under its class.
    protected function get_cache_key () {

        $name = parent::get_cache_key();
        $held = is_null($name) ? null : (self::$instances[static::get_cache_group()][$name] ?? null);

        return ($held && ($held !== $this) && !($held instanceof static)) ? static::class : $name;

    }

    public function run () {}

    public function get_hooks () {

        return [];

    }

}
