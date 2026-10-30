<?php

declare(strict_types=1);

namespace Lattice;

use \WC_Order_Query;

abstract class Order_Iterator extends Iterator {

    protected $labels = [
        'single'    => 'order',
        'plural'    => 'orders',
    ];

    public function process_order ($order) {}

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

        $query = new WC_Order_Query($this->get_query_vars($this->get_default_query_vars()));

        return $query->get_orders();

    }

    public function get_total_items () {

        $args = wp_parse_args([
            'limit'    => 1, // the paginated result's total; one row is fetched instead of every id
            'offset'   => false,
            'paginate' => true,
            'return'   => 'ids',
        ], $this->get_query_vars($this->get_default_query_vars()));

        $result = (new WC_Order_Query($args))->get_orders();

        return is_object($result) ? (int) $result->total : null; // a data store that ignores paginate answers the one row, not a total: unknown

    }

    public function get_item_id ($item) {

        return $item->get_id();

    }

    public function process_item ($item) {

        return $this->process_order($item);

    }

}