<?php

declare(strict_types=1);

namespace Lattice;

use Automattic\WooCommerce\Caches\OrderCache;
use Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore;
use Automattic\WooCommerce\Utilities\OrderUtil;
use WC_Order;
use WC_Order_Query;
use WP_Error;

class Order extends Model {

    use Has_Fields;

    protected $wc_order;

    public static function extract_id ($data = null) {

        if ($data instanceof WC_Order) return $data->get_id();

        return parent::extract_id($data);

    }

    public static function validate_id ($id) {

        return (bool) wc_get_order($id);

    }

    public static function query ($args = [], &$results = null) {
    
        $query   = new WC_Order_Query($args);
        $results = $query->get_orders();

        if (is_array($results)) {

            return static::get_instances($results);

        } else {

            // When 'paginate' is true, woo returns stdClass with orders, total, max_num_pages

            return static::get_instances($results->orders);

        }
    
    }

    protected function build_instance ($data) {

        if (is_array($data)) $data = (object) $data;

        $this->wc_order = new \WC_Order($data);

    }

    protected function hydrate_instance () {

        $this->wc_order = wc_get_order($this->id);

    }

    public function __call ($name, $args) {
    
        return call_user_func_array([$this->get_wc_order(), $name], $args);
    
    }

    public function get_wc_order () {
    
        return $this->wc_order;
    
    }

    public function get_field_id () {

        return $this->get_id();

    }

    // WC_Abstract_Order::__clone() is empty, so a cloned WC_Order shares its meta and items with the original.
    public function __clone () {

        $this->wc_order = $this->wc_order->get_id() ? new WC_Order($this->wc_order->get_id()) : clone $this->wc_order;

    }

    // WC_Order::save() catches its own exceptions and answers the id either way; only the after-save hook tells.
    public function save () {

        $order = $this->get_wc_order();
        $type  = (fn () => $this->object_type)->call($order);
        $hook  = "woocommerce_after_{$type}_object_save";
        $saved = false;
        $watch = function ($object) use ($order, &$saved) { if ($object === $order) $saved = true; };

        add_action($hook, $watch);

        try {

            $id = $order->save();

        } finally {

            remove_action($hook, $watch);

        }

        if (!$saved) return new WP_Error('not_saved', 'The order was not saved.');

        if ($this->is_new()) {

            $this->is_new = false;
            $this->id     = $id;

            $this->cache_instance();

        }

        return $id;

    }

    // WC_Data::delete() zeroes the order's id even when it only trashes.
    public function reload () {

        $this->clear_cache();

        $this->wc_order = wc_get_order($this->get_id()) ?: $this->wc_order;

    }

    public function clear_cache () : void {

        wc_get_container()->get(OrderCache::class)->remove($this->get_id());

        if (OrderUtil::custom_orders_table_usage_is_enabled()) wc_get_container()->get(OrdersTableDataStore::class)->clear_cached_data([$this->get_id()]);

    }

}