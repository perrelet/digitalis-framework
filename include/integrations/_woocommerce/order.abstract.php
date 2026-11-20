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

    // With HPOS off an order is also a post, so a Post save and an Order save follow each other; Has_WC_Data follows the order's own saves.
    protected static function get_record_rows (int|string $id) : array {

        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? [] : [['posts', $id]];

    }

    protected static function get_record_storage () : string {

        return 'wc_order';

    }

    protected static function get_record_properties () : array {

        return [...parent::get_record_properties(), 'wc_order'];

    }

    use Has_Fields, Has_WC_Data;

    const WC_DATA = 'wc_order';

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

        $this->hold_wc_data();

    }

    protected function hydrate_instance () {

        $this->wc_order = wc_get_order($this->id);

        $this->hold_wc_data();

    }

    public function get_wc_order () {
    
        return $this->wc_order;
    
    }

    public function get_field_id () {

        return $this->get_id();

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

            if ($this->is_new()) $this->get_record()->state['match'] = fn ($id) => $id && ((int) $order->get_id() === (int) $id);

            $id = $this->is_new() ? Model_Record::insert($this->get_record(), fn () => $order->save()) : $order->save();

        } finally {

            remove_action($hook, $watch);

        }

        if (!$saved) return new WP_Error('not_saved', 'The order was not saved.');

        if ($this->is_new()) {

            $this->is_new = false;
            $this->id     = $id;

            $this->cache_instance();

            // Status transition callbacks save copies of the new id before save() returns, while no model is cached under it.
            if (!$this->is_dirty()) $this->reload();

        }

        $this->follow_save();

        return $id;

    }

    public function clear_cache () : void {

        wc_get_container()->get(OrderCache::class)->remove($this->get_id());

        // Woo 8.6 has no clear_cached_data().
        if (OrderUtil::custom_orders_table_usage_is_enabled() && method_exists($store = wc_get_container()->get(OrdersTableDataStore::class), 'clear_cached_data')) $store->clear_cached_data([$this->get_id()]);

    }

}