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

    public static function query ($args = []) {
    
        $query = new WC_Order_Query($args);
        $wc_orders = $query->get_orders();

        return static::get_instances($wc_orders);
    
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