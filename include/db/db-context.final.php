<?php

namespace Lattice\DB;

use wpdb;

final class DB_Context {

    public static function get_wpdb () : wpdb {

        global $wpdb;

        $wpdb = \Lattice\Hook::filter('lattice.db.wpdb', $wpdb);

        if (!$wpdb instanceof wpdb) throw new \RuntimeException('digitalis/db/wpdb filter must return wpdb');

        return $wpdb;

    }

}