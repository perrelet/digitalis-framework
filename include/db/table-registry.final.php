<?php

namespace Digitalis\DB;

use wpdb;

final class Table_Registry {

    private array $tables_by_slug  = [];
    private array $tables_by_class = [];

    public function __construct (array $tables = []) {

        foreach ($tables as $table_class) $this->register($table_class);

    }

    public function register (string $table_class) : void {

        if (!is_subclass_of($table_class, Table::class)) throw new \InvalidArgumentException("Table_Registry: {$table_class} must implement " . Table::class);

        $slug = $table_class::get_slug();

        if (isset($this->tables_by_slug[$slug])) throw new \RuntimeException("Table_Registry: duplicate slug '{$slug}' for {$table_class}, already registered by {$this->tables_by_slug[$slug]}");

        $this->tables_by_slug[$slug]         = $table_class;
        $this->tables_by_class[$table_class] = true;

    }

    public function has (string $table_class) : bool {

        return isset($this->tables_by_class[$table_class]);

    }

    public function resolve_name (wpdb $wpdb, string $table_class) : string {

        if (!$this->has($table_class)) throw new \RuntimeException("Table_Registry: table not registered: {$table_class}");

        return $table_class::get_name($wpdb);

    }

    public function get_all () : array {

        return array_values($this->tables_by_slug);

    }

    public function get_all_by_slug () : array {

        return $this->tables_by_slug;

    }

}