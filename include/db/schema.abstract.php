<?php

namespace Digitalis\DB;

abstract class Schema {

    protected static $slug;
    protected static $version;

    public static function get_slug () : string {

        return static::$slug;

    }

    public static function get_version () : int {

        return static::$version;

    }

    public static function get_migrations () : array {

        return [];

    }

    public static function get_tables () : array {

        return [];

    }

}