<?php

declare(strict_types=1);

namespace Lattice;

final class Journal_Schema extends DB\Schema {

    protected static $slug    = 'lattice_journal';
    protected static $version = 1;

    const BACKOFF = 10 * MINUTE_IN_SECONDS;

    // The runner rethrows: back off rather than fail every request.
    public static function ensure () : void {

        $backoff = 'lattice_journal_schema_backoff';

        if (get_transient($backoff)) return;

        try {

            (new DB\Migration_Runner(new DB\Table_Registry(), new DB\Option_Migration_Logger()))->migrate_module(static::class);

        } catch (\Throwable $e) {

            set_transient($backoff, 1, static::BACKOFF);

            error_log('Lattice journal schema: ' . $e->getMessage());

        }

    }

    public static function is_current () : bool {

        return (int) get_option('digitalis_schema_' . static::$slug) >= static::$version;

    }

    public static function get_tables () : array {

        return [Journal_Table::class];

    }

    public static function get_migrations () : array {

        return [1 => V1_Create_Journal::class];

    }

}
