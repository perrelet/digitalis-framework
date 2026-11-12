<?php

declare(strict_types=1);

namespace Lattice\DB;

use wpdb;

final class Migration_Runner {

    private const LOCK_TTL_SECONDS = 120;

    public function __construct(
        private /* readonly */ Table_Registry    $tables,
        private /* readonly */ ?Migration_Logger $logger = null, /* new Migration_Logger() */
        private /* readonly */ ?wpdb             $wpdb   = null
    ) {

        $this->tables = $tables;
        $this->logger = $logger ?? new Migration_Logger();
        $this->wpdb   = $wpdb   ?? DB_Context::get_wpdb();

    }

    public function migrate_module (string $schema_class) : void {

        if (!is_subclass_of($schema_class, Schema::class)) {
            throw new \InvalidArgumentException("Migration_Runner: {$schema_class} must implement " . Schema::class);
        }

        $module_slug = $schema_class::get_slug();

        $this->register_module_tables($schema_class);

        $installed = $this->installed_version($module_slug);
        $target    = (int) $schema_class::get_version();

        if ($installed >= $target) return;

        $lock_key = $this->get_lock_key($module_slug);

        if (!$this->acquire_lock($lock_key)) return;

        $module_started = microtime(true);

        try {

            // Read again under the lock: a request that waited behind another's run would otherwise run it again.
            if (($installed = (int) $this->read_option($this->get_option_key($module_slug))) >= $target) return;

            $migrations = $schema_class::get_migrations();
            $this->assert_migrations_are_valid($module_slug, $migrations);

            ksort($migrations);

            $ctx = new Schema_Context(
                wpdb:        $this->wpdb,
                tables:      $this->tables,
                module_slug: $module_slug,
                is_network:  false
            );

            foreach ($migrations as $to_version => $migration_class) {

                $to_version = (int) $to_version;

                if ($to_version <= $installed) continue;
                if ($to_version > $target)     break;

                $migration_started = microtime(true);

                $this->logger->info('Running migration', [
                    'module' => $module_slug,
                    'to'     => $to_version,
                    'class'  => $migration_class,
                ]);

                $migration = new $migration_class();

                if (!$migration instanceof Migration) {
                    throw new \RuntimeException("Migration_Runner: {$migration_class} must implement " . Migration::class);
                }

                $migration->up($ctx);

                $this->set_installed_version($module_slug, $to_version);
                $installed = $to_version;

                $this->logger->info('Migration complete', [
                    'module'  => $module_slug,
                    'to'      => $to_version,
                    'seconds' => round(microtime(true) - $migration_started, 4),
                ]);

            }

            $this->logger->info('Module migrations finished', [
                'module'  => $module_slug,
                'seconds' => round(microtime(true) - $module_started, 4),
            ]);

        } catch (\Throwable $e) {

            $this->logger->error('Migration failed', [
                'module' => $module_slug,
                'error'  => $e->getMessage(),
                'class'  => get_class($e),
                'trace'  => $this->trim_trace($e->getTraceAsString()),
            ]);

            throw $e;

        } finally {

            $this->release_lock($lock_key);

        }

    }

    public function uninstall_module (
        string $schema_class,
        bool   $drop_tables  = false,
        bool   $clear_logger = false
    ) : void {

        if (!is_subclass_of($schema_class, Schema::class)) throw new \InvalidArgumentException("Migration_Runner: {$schema_class} must extend " . Schema::class);

        $module_slug = $schema_class::get_slug();

        $this->register_module_tables($schema_class);

        $lock_key = $this->get_lock_key($module_slug);

        if (!$this->acquire_lock($lock_key)) {
            $this->logger->warning('Uninstall lock present, skipping', [
                'module' => $module_slug,
                'lock'   => $lock_key,
            ]);
            return;
        }

        try {

            if ($drop_tables) {

                $ctx = new Schema_Context(
                    wpdb:        $this->wpdb,
                    tables:      $this->tables,
                    module_slug: $module_slug,
                    is_network:  false
                );

                foreach ($schema_class::get_tables() as $table_class) {
                    $this->logger->warning('Dropping table', [
                        'module'      => $module_slug,
                        'table_class' => $table_class,
                        'table'       => $table_class::get_name($this->wpdb),
                    ]);
                    $ctx->drop_table($table_class);
                }

            }

            delete_option($this->get_option_key($module_slug));
            delete_option($lock_key);

            $this->logger->info('Module uninstalled', [
                'module'      => $module_slug,
                'drop_tables' => $drop_tables,
            ]);

            if ($clear_logger && method_exists($this->logger, 'clear')) $this->logger->clear();

        } finally {

            $this->release_lock($lock_key);

        }

    }


    private function trim_trace (string $trace) : string {

        return implode("\n", array_slice(explode("\n", $trace), 0, 20));

    }

    private function register_module_tables (string $schema_class) : void {

        foreach ($schema_class::get_tables() as $table_class) $this->tables->register($table_class);

    }

    private function assert_migrations_are_valid (string $module_slug, array $migrations) : void {

        foreach ($migrations as $version => $migration_class) {

            if (!is_int($version) && !ctype_digit((string) $version)) {
                throw new \InvalidArgumentException("Migration_Runner: {$module_slug} migrations keys must be ints, got: " . gettype($version));
            }

            if (!is_string($migration_class) || !class_exists($migration_class)) {
                throw new \InvalidArgumentException("Migration_Runner: {$module_slug} migration class not found: " . print_r($migration_class, true));
            }

        }

    }

    private function get_option_key (string $module_slug) : string {

        return "digitalis_schema_{$module_slug}";

    }

    private function get_lock_key (string $module_slug) : string {

        return "digitalis_schema_lock_{$module_slug}";

    }

    private function installed_version (string $module_slug) : int {

        $value = get_option($this->get_option_key($module_slug), 0);
        return (int) $value;

    }

    private function set_installed_version (string $module_slug, int $version) : void {

        wp_cache_delete('alloptions', 'options');

        update_option($this->get_option_key($module_slug), (int) $version, true);

    }

    // add_option upserts, so two requests could both believe they hold it. An ignored insert has one winner, and a stale lock is taken by the one update that still finds what it read.
    private function acquire_lock (string $lock_key) : bool {

        $now     = time();
        $options = $this->wpdb->options;

        if ($this->wpdb->query($this->wpdb->prepare("INSERT IGNORE INTO {$options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $lock_key, $now))) return true;

        $existing = $this->read_option($lock_key);

        if (is_null($existing) || (($now - (int) $existing) < self::LOCK_TTL_SECONDS)) return false;

        return (bool) $this->wpdb->query($this->wpdb->prepare("UPDATE {$options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $now, $lock_key, $existing));

    }

    private function read_option (string $name) : ?string {

        return $this->wpdb->get_var($this->wpdb->prepare("SELECT option_value FROM {$this->wpdb->options} WHERE option_name = %s", $name));

    }

    private function release_lock (string $lock_key) : void {

        delete_option($lock_key);

    }

}