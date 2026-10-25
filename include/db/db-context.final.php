<?php

declare(strict_types=1);

namespace Lattice\DB;

use wpdb;

final class DB_Context {

    public static function get_wpdb () : wpdb {

        global $wpdb;

        $wpdb = \Lattice\Hook::filter('lattice.db.wpdb', $wpdb);

        if (!$wpdb instanceof wpdb) throw new \RuntimeException('lattice.db.wpdb filter must return wpdb');

        return $wpdb;

    }

}