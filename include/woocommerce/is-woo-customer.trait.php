<?php

namespace Digitalis;

use \WC_Customer;
use \WC_Order_Item_Product;

trait Is_Woo_Customer {

    protected $customer;

    // https://woocommerce.github.io/code-reference/classes/WC-Customer.html

    public function get_customer () {

        if (is_null($this->customer)) {

            $this->customer = new WC_Customer($this->id);

        }

        return $this->customer;

    }

    public function get_orders ($args = []) {

        $args = wp_parse_args($args, [
            'status'        => 'paid',
        ]);

        $args['customer_id'] = $this->id;

        if ($args['status'] == 'paid')              $args['status'] = wc_get_is_paid_statuses();
        if ($args['status'] == 'pending')           $args['status'] = wc_get_is_pending_statuses();
        if ($args['status'] == 'paid_and_pending')  $args['status'] = array_merge(wc_get_is_paid_statuses(), wc_get_is_pending_statuses());

        return wc_get_orders($args);

    }

    public function has_ordered ($product_id) {

        if ($orders = $this->get_orders()) foreach ($orders as $order) {
            
            if ($items = $order->get_items()) foreach ($items as $item) {

                if (($item instanceof WC_Order_Item_Product) && ($product_id == $item->get_product_id())) return $order;

            }

        }

        return false;

    }

}