<?php

namespace Digitalis;

final class Jobs_Schema extends DB\Schema {

    protected static $slug    = 'lattice_jobs';
    protected static $version = 1;

    public static function forget () : void {

        delete_option('digitalis_schema_' . static::$slug);
        delete_transient('digitalis_schema_backoff_' . static::$slug);

    }

    public static function get_tables () : array {

        return [Jobs_Table::class];

    }

    public static function get_migrations () : array {

        return [1 => V1_Create_Jobs::class];

    }

}
