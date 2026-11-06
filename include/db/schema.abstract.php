<?php

declare(strict_types=1);

namespace Lattice\DB;

abstract class Schema {

    protected static $slug;
    protected static $version;

    const BACKOFF = 10 * MINUTE_IN_SECONDS;

    // For a feature that keeps tables of its own. The runner rethrows: back off rather than fail every request.
    public static function ensure () : void {

        $backoff = 'digitalis_schema_backoff_' . static::$slug;

        if (get_transient($backoff)) return;

        try {

            (new Migration_Runner(new Table_Registry(), new Option_Migration_Logger()))->migrate_module(static::class);

        } catch (\Throwable $e) {

            set_transient($backoff, 1, static::BACKOFF);

            error_log('Schema ' . static::$slug . ': ' . $e->getMessage());

        }

    }

    public static function is_current () : bool {

        return (int) get_option('digitalis_schema_' . static::$slug) >= static::$version;

    }

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