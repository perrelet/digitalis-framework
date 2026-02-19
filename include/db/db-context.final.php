<?php

namespace Digitalis\DB;

use wpdb;

final class DB_Context {

    public static function get_wpdb () : wpdb {

        global $wpdb;

        $wpdb = apply_filters('Digitalis/DB/WPDB', $wpdb);

        if (!$wpdb instanceof wpdb) throw new \RuntimeException('digitalis/db/wpdb filter must return wpdb');

        return $wpdb;

    }

}