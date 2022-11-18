<?php

namespace Digitalis;

use \WC_Customer;

trait Is_Woo_Customer {

    protected $customer;

    // https://woocommerce.github.io/code-reference/classes/WC-Customer.html

    public function get_customer () {

        if (is_null($this->customer)) {

            $this->customer = new WC_Customer($this->id);

        }

        return $this->customer;

    }

}