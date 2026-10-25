<?php

declare(strict_types=1);

namespace Lattice;

use \WC_Order_Item;
use \WC_Order_Item_Product;

/**
 * @mixin \WC_Order_Item_Product   (every call __call forwards reaches the item, typed from the stubs; the row below overrides the one return type the stub gets wrong)
 * @method \WC_Order|\WC_Order_Refund|false get_order()   // WC_Order_Item_Product::get_order() is wc_get_order(), which the stub types false|WC_Order|WC_Order_Refund where WC_Order_Item::get_order() says WC_Order; the callers guard for false and for a refund
 */
class Order_Item extends Model {

    protected static $product_type = null;

    public static function extract_id ($data = null) {

        if ($data instanceof WC_Order_Item) return $data->get_id();

        return parent::extract_id($data);

    }

    public static function validate_id ($id) {

        if (!static::exists($id)) return false; // WC_Order_Item_Product's constructor throws "Invalid order item" for an unknown id and wraps an empty item for a non-numeric one, where a model answers null

        if (!static::$product_type) return true;

        $wc_item = new WC_Order_Item_Product($id);
        return (($wc_product = $wc_item->get_product()) && ($wc_product->get_type() == static::$product_type));

    }

    public static function exists ($item_id) {

        global $wpdb;

        if (!is_numeric($item_id)) return false; // %d would read a request's '7abc' as 7; numeric values pass as extract_id() casts them, so a consumer's exists() then get_instance() agree on every value

        return (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d", $item_id));

    }

    //

    protected $item;
    protected $siblings;

    protected function build_instance ($data) {

        if (is_array($data)) $data = (object) $data;

        $this->item = new \WC_Order_Item_Product($data);

    }

    protected function hydrate_instance () {

        $this->item = new \WC_Order_Item_Product($this->id);

    }

    public function __call ($name, $args) {
    
        return call_user_func_array([$this->get_item(), $name], $args);
    
    }

    public function get_item () {

        return $this->item;

    }

    public function get_item_id () {

        return $this->item->get_id();

    }

    public function get_group_id () { // !!! Eventropy

        return $this->get_item()->get_meta('_group', true);

    }

    public function get_customer_id () {

        return (($wc_order = $this->get_order()) && method_exists($wc_order, 'get_customer_id')) ? $wc_order->get_customer_id() : null; // Refunded orders dont have get_customer_id method

    }

    public function get_order_status () {

        return ($wc_order = $this->get_order()) ? $wc_order->get_status() : null;

    }

    public function get_date ($event = 'created', $format = 'Y-m-d H:i:s') {

        if (!method_exists($this, $getter = "get_date_{$event}") || !$date = $this->$getter()) return null; // an unknown event would reach __call and the item, which has no such method
        return $format ? $date->format($format) : $date;

    }

    public function get_date_completed () {

        return ($wc_order = $this->get_order()) ? $wc_order->get_date_completed() : null;

    }

    public function get_date_created () {

        return ($wc_order = $this->get_order()) ? $wc_order->get_date_created() : null;

    }

    public function get_date_modified () {

        return ($wc_order = $this->get_order()) ? $wc_order->get_date_modified() : null;

    }

    public function get_date_paid () {

        return ($wc_order = $this->get_order()) ? $wc_order->get_date_paid() : null;

    }

    //

    public function get_siblings ($product_type = false) { // !!! Eventropy

        if (!$group_id = $this->get_group_id()) return [];

        if (is_null($this->siblings)) {

            $this->siblings = [];

            if (($wc_order = $this->get_order()) && ($wc_items = $wc_order->get_items())) foreach ($wc_items as $wc_item) {

                if ($wc_item->get_id() == $this->get_item_id()) continue;

                if (($sibling_group_id = $wc_item->get_meta('_group', true)) && ($sibling_group_id == $group_id)) {

                    $this->siblings[] = $wc_item;

                }

            }

        }
        
        if ($product_type) {

            $filtered = [];

            if ($this->siblings) foreach ($this->siblings as $wc_sibling) {

                if (($wc_product = $wc_sibling->get_product()) && ($wc_product->get_type() == $product_type)) $filtered[] = $wc_sibling;

            }

            return $filtered;

        } else {

            return $this->siblings;

        }

    }

}