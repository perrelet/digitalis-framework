<?php

namespace Digitalis;

use WC_Order;

class Order extends Model {

    protected $wc_order;

    public static function extract_id ($data = null) {

        if ($data instanceof WC_Order) return $data->get_id();

        return parent::extract_id($data);

    }

    public static function validate ($data, $uid, $id) {

        if (!($data instanceof WC_Order)) return (bool) wc_get_order($id);

    }

    public function __construct ($data = null, $uid = null, $id = null) {

        parent::__construct($data, $uid, $id);

        if ($this->data instanceof WC_Order) {

            $this->wc_order = $this->data;

        } else {

            $this->wc_order = wc_get_order($this->id);

        }

    }

    public function __call ($name, $args) {
    
        return call_user_func_array([$this->wc_order, $name], $args);
    
    }

    public function get_wc_order () {
    
        return $this->wc_order;
    
    }

}