<?php

namespace Digitalis;

final class Jobs_Schema extends DB\Schema {

    protected static $slug    = 'lattice_jobs';
    protected static $version = 1;

    const BACKOFF     = 10 * MINUTE_IN_SECONDS;
    const BACKOFF_KEY = 'lattice_jobs_schema_backoff';

    // The runner rethrows.
    public static function ensure () : void {

        if (get_transient(static::BACKOFF_KEY)) return;

        try {

            (new DB\Migration_Runner(new DB\Table_Registry(), new DB\Option_Migration_Logger()))->migrate_module(static::class);

        } catch (\Throwable $e) {

            set_transient(static::BACKOFF_KEY, 1, static::BACKOFF);

            error_log('Lattice jobs schema: ' . $e->getMessage());

        }

    }

    public static function is_current () : bool {

        return (int) get_option('digitalis_schema_' . static::$slug) >= static::$version;

    }

    public static function forget () : void {

        delete_option('digitalis_schema_' . static::$slug);
        delete_transient(static::BACKOFF_KEY);

    }

    public static function get_tables () : array {

        return [Jobs_Table::class];

    }

    public static function get_migrations () : array {

        return [1 => V1_Create_Jobs::class];

    }

}
