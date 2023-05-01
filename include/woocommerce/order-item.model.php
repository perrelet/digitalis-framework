<?php

namespace Digitalis;

use \WC_Order_Item;
use \WC_Order_Item_Product;

class Order_Item extends Model {

    protected static $product_type = null;

    public static function extract_id ($id = null) {

        if ($id instanceof WC_Order_Item) return $id->get_id();

        return parent::extract_id($id);

    }

    public static function validate ($data) {

        if (static::$product_type) {

            $item = ($data instanceof WC_Order_Item) ? $data : new WC_Order_Item_Product($data);
            return ($item && $product = $item->get_product() && ($product->get_type() == static::$product_type));

        } else {

            return true;

        }

    }

    public static function exists ($item_id) {

        global $wpdb;

        $items_table = "{$wpdb->prefix}woocommerce_order_items";

        $sql = "SELECT 'order_item_id' FROM {$items_table} WHERE order_item_id = '%d'";

        return (bool) $wpdb->get_row($wpdb->prepare($sql, $item_id));

    }

    public static function get_from_item ($item) {
        
        return static::get_instance($item);

    }

    //

    protected $item;
    protected $siblings;

    public function init ($item = null) {

        if ($item instanceof WC_Order_Item) {

            $this->item = $item;

        } else {

            // https://github.com/woocommerce/woocommerce/issues/35548
            // https://github.com/woocommerce/woocommerce/issues/30603

            $this->item = new WC_Order_Item_Product($item);

        }

    }

    public function get_item () {

        return $this->item;

    }

    public function get_item_id () {

        return $this->item->get_id();

    }

    public function get_name () {

        return $this->item->get_name();

    }

    public function get_group_id () {

        return $this->get_item()->get_meta('_group', true);

    }

    public function get_customer_id () {

        return method_exists($this->get_order(), 'get_customer_id') ? $this->get_order()->get_customer_id() : null; // Refunded orders dont have get_customer_id method

    }

    public function get_order_id () {

        return $this->item->get_order_id();

    }

    public function get_order () {

        return $this->item->get_order();

    }

    public function get_order_status () {

        return $this->get_order() ? $this->get_order()->get_status() : null;

    }

    public function get_product_id () {

        return $this->item->get_product_id();

    }

    public function get_date ($event = 'created', $format = 'Y-m-d H:i:s') {

        if (!$date = $this->{"get_date_{$event}"}) return null;
        return $format ? $date->format($format) : $date;

    }

    public function get_date_completed () {

        return $this->get_order() ? $this->get_order()->get_date_completed() : null;

    }

    public function get_date_created () {

        return $this->get_order() ? $this->get_order()->get_date_created() : null;

    }

    public function get_date_modified () {

        return $this->get_order() ? $this->get_order()->get_date_modified() : null;

    }

    public function get_date_paid () {

        return $this->get_order() ? $this->get_order()->get_date_paid() : null;

    }

    //

    public function get_siblings ($product_type = false) {

        if (!$group_id = $this->get_group_id()) return [];

        if (is_null($this->siblings)) {

            $this->siblings = [];

            if ($items = $this->get_order()->get_items()) foreach ($items as $item) {

                if ($item->get_id() == $this->get_item_id()) continue;

                if (($sibling_group_id = $item->get_meta('_group', true)) && ($sibling_group_id == $group_id)) {

                    $this->siblings[] = $item;

                }

            }

        }
        
        if ($product_type) {

            $filtered = [];

            if ($this->siblings) foreach ($this->siblings as $sibling) {

                if ($sibling->get_product()->get_type() == $product_type) $filtered[] = $sibling;

            }

            return $filtered;

        } else {

            return $this->siblings;

        }

    }

}