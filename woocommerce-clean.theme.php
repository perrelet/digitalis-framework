<?php

namespace Digitalis;

abstract class Woocommerce_Clean_Theme extends Woocommerce_Theme {

    public function __construct() {

        add_action('woocommerce_checkout_before_customer_details', function () { echo "<div class='customer-details-wrap'>"; }, PHP_INT_MIN);
        add_action('woocommerce_checkout_before_customer_details', function () { echo "</div><!-- .customer-details-wrap -->"; }, PHP_INT_MAX);

        add_action('woocommerce_checkout_after_customer_details', function () { echo "<div class='after-customer-details-wrap'>"; }, PHP_INT_MIN);
        add_action('woocommerce_checkout_after_customer_details', function () { echo "</div><!-- .after-customer-details-wrap -->"; }, PHP_INT_MAX);

        add_action('woocommerce_checkout_before_order_review_heading', function () { echo "<div class='checkout-order-review-wrap'>"; });
        add_action('woocommerce_checkout_after_order_review', function () { echo "</div><!-- .checkout-order-review-wrap -->"; });

        parent::__construct();
        
    }

}