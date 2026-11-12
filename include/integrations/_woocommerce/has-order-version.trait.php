<?php

declare(strict_types=1);

namespace Lattice;

use Automattic\WooCommerce\Utilities\OrderUtil;

// Storage straight to the meta table: a WC_Order holds its meta in memory until it saves, and its table has no unique key to make a first claim atomic.
trait Has_Order_Version {

    use Has_Version;

    public function get_woo_version () {

        return $this->get_wc_order()->get_version();

    }

    protected function read_version () : int {

        global $wpdb;

        $table = OrderUtil::get_table_for_order_meta();

        return (int) $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$table} WHERE order_id = %d AND meta_key = %s ORDER BY id LIMIT 1", $this->get_id(), static::get_version_key()));

    }

    protected function swap_version (int $seen, int $next) : bool {

        global $wpdb;

        $table = OrderUtil::get_table_for_order_meta();

        if ($seen) {

            $swapped = $wpdb->query($wpdb->prepare("UPDATE {$table} SET meta_value = %s WHERE order_id = %d AND meta_key = %s AND meta_value = %s", (string) $next, $this->get_id(), static::get_version_key(), (string) $seen)) === 1;

        } else {

            $lock = "lattice_version_{$this->get_id()}";

            if (!$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock))) return false;

            $swapped = !$this->read_version() && $wpdb->insert($table, ['order_id' => $this->get_id(), 'meta_key' => static::get_version_key(), 'meta_value' => (string) $next]);

            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));

        }

        $this->clear_cache();

        return $swapped;

    }

    protected function drop_version (int $seen) : bool {

        global $wpdb;

        $table   = OrderUtil::get_table_for_order_meta();
        $dropped = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE order_id = %d AND meta_key = %s AND meta_value = %s", $this->get_id(), static::get_version_key(), (string) $seen)) === 1;

        $this->clear_cache();

        return $dropped;

    }

}
