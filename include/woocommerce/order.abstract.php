<?php

namespace Digitalis;

use WC_Order;

class Order extends WC_Order { // Requires use of 'woocommerce_order_class' filter - Consider: extend from Digitalis\Model instead...

    public static function get_instance ($id = null) {
    
        return wc_get_order($id);
    
    }

    public static function inst ($id = null) {
    
        return static::get_instance($id);
    
    }

}