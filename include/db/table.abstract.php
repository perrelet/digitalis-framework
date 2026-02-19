<?php

namespace Digitalis\DB;

use wpdb;

abstract class Table {

    protected static $slug      = 'custom-table';
    protected static $base_name = 'digitalis_custom_table';
    protected static $scope     = 'site';

    public const SCOPE_SITE    = 'site';
    public const SCOPE_NETWORK = 'network';

    public static function get_slug () : string {

        return static::$slug;

    }

    public static function get_base_name () : string {

        return static::$base_name;

    }

    public static function get_scope () : string {

        return static::$scope;

    }

    public static function get_name (wpdb $wpdb) : string {

        $scope = static::get_scope();
        if (!in_array($scope, [self::SCOPE_SITE, self::SCOPE_NETWORK])) throw new \RuntimeException(static::class . " has invalid scope '{$scope}'");

        $prefix = ($scope === self::SCOPE_SITE) ? $wpdb->prefix : $wpdb->base_prefix;

        return $prefix . static::get_base_name();

    }

    public static function get_name_from_context () : string {

        return static::get_name(DB_Context::get_wpdb());

    }

    public static function get_columns () : array {
    
        return [];
    
    }

    public static function get_indexes () : array {
    
        return [];
    
    }

    public static function create_sql (wpdb $wpdb) : string {

        $name            = static::get_name($wpdb);
        $charset_collate = $wpdb->get_charset_collate();

        $column_sql = array_map(
            fn (Column $c) => $c->to_sql(),
            static::get_columns()
        );

        $index_sql = array_map(
            fn (Index $i) => $i->to_sql(),
            static::get_indexes()
        );

        if (!$column_sql) throw new \RuntimeException(static::class . " defines no columns");

        $defs = array_merge($column_sql, $index_sql);
        $defs = implode(",\n    ", $defs);

        return "CREATE TABLE `{$name}` (\n    {$defs}\n) {$charset_collate};";

    }

    public static function drop_sql (wpdb $wpdb) : string {

        $name = static::get_name($wpdb);

        return "DROP TABLE IF EXISTS `{$name}`;";

    }

}