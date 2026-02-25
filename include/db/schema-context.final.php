<?php

namespace Digitalis\DB;

use wpdb;

final class Schema_Context {

    public function __construct(
        public /* readonly */ wpdb           $wpdb,
        public /* readonly */ Table_Registry $tables,
        public /* readonly */ string         $module_slug,
        public /* readonly */ bool           $is_network = false
    ) {}

    public function get_table_name (string $table_class) : string {

        return $this->tables->resolve_name($this->wpdb, $table_class);

    }

    public function create_table_sql (string $table_class) : string {

        if (!$this->tables->has($table_class)) throw new \RuntimeException("Schema_Context: table not registered: {$table_class}");

        return $table_class::create_sql($this->wpdb);

    }

    public function drop_table_sql (string $table_class) : string {

        if (!$this->tables->has($table_class)) throw new \RuntimeException("Schema_Context: table not registered: {$table_class}");

        return $table_class::drop_sql($this->wpdb);

    }

    public function get_charset_collate () : string {

        return $this->wpdb->get_charset_collate();

    }

    public function query (string $sql) : void {

        $result = $this->wpdb->query($sql);

        if ($result === false) {
            $error = $this->wpdb->last_error ?: 'Unknown SQL error';
            throw new \RuntimeException("Schema_Context: SQL failed: {$error}\nSQL: {$sql}");
        }

    }

    public function get_var (string $sql) : mixed {

        return $this->wpdb->get_var($sql);

    }

    public function table_exists (string $table_name) : bool {

        $like = $this->wpdb->esc_like($table_name);
        $sql  = $this->wpdb->prepare("SHOW TABLES LIKE %s", $like);

        return (bool) $this->wpdb->get_var($sql);

    }

    public function registered_table_exists(string $table_class) : bool {

        return $this->table_exists($this->get_table_name($table_class));

    }

    public function column_exists (string $table_name, string $column) : bool {

        $sql = $this->wpdb->prepare("SHOW COLUMNS FROM `{$table_name}` LIKE %s", $column);

        return (bool) $this->wpdb->get_var($sql);

    }

    public function index_exists (string $table_name, string $index_name) : bool {

        $sql = $this->wpdb->prepare("SHOW INDEX FROM `{$table_name}` WHERE Key_name = %s", $index_name);

        return (bool) $this->wpdb->get_var($sql);

    }

    public function drop_table (string $table_class) : void {

        $this->query($this->drop_table_sql($table_class));

    }

}