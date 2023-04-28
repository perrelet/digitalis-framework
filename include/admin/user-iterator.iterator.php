<?php

namespace Digitalis;

use \WP_User_Query;

abstract class User_Iterator extends Iterator {

    public function get_default_query_args () {

        return [
            'number'    => $this->batch_size,
            'offset'    => $this->index,
            'orderby'   => 'ID',
            'order'     => 'ASC',
        ];

    }

    public function get_query_args ($args) {

        return $args;

    }

    public function get_items () {

        $query = new WP_User_Query($this->get_query_args($this->get_default_query_args()));

        return $query->get_results();

    }

    public function get_total_items () {

        return get_user_count();

    }

    public function get_item_id ($item) {

        return $item->ID;

    }

    public function process_item ($item) {

        return $this->process_user($item);

    }

    public function process_user ($wp_user) {}

}