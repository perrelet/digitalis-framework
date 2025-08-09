<?php

namespace Digitalis;

use \WC_Product_Query;

abstract class Product_Iterator extends Iterator {

    protected $labels = [
        'single'    => 'product',
        'plural'    => 'products',
    ];

    public function process_product ($product) {}

    //

    public function get_query_vars ($vars) {

        return $vars;

    }

    public function get_default_query_vars () {

        return [
            'limit'   => $this->batch_size,
            'offset'  => $this->index,
            'orderby' => 'ID',
            'order'   => 'ASC',
        ];

    }

    public function get_items () {

        $query = new WC_Product_Query($this->get_query_vars($this->get_default_query_vars()));

        return $query->get_products();

    }

    public function get_total_items () {

        $args = wp_parse_args([
            'limit'  => -1,
            'offset' => false,
            'return' => 'ids',
        ], $this->get_query_vars($this->get_default_query_vars()));

        return count((new WC_Product_Query($args))->get_products());

    }

    public function get_item_id ($item) {

        return $item->get_id();

    }

    public function process_item ($item) {

        return $this->process_product($item);

    }

}