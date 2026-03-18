<?php

namespace Digitalis;

use WC_Order;
use WC_Order_Query;

class Order extends Model {

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

}