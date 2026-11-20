<?php

declare(strict_types=1);

namespace Lattice;

abstract class Feature extends Registrant {

    protected static $cache_group    = self::class;
    protected static $cache_property = 'id';

    protected $id = null;

    // Library feature paths that load_feature() loads first.
    protected static $requires = [];

    public static function get_requirements () : array {

        return static::$requires;

    }

    public static function load () {

        return func_num_args() ? static::declare(...func_get_args()) : static::get_instance();

    }

    // A feature built by any path has its requirements, as through load_feature().
    protected static function prepare_build () : bool {

        foreach (static::get_requirements() as $requirement) if (!Framework::get_instance()?->load_feature($requirement)) return false;

        return true;

    }

}
