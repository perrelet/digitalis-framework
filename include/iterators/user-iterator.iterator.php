<?php

namespace Digitalis;

use \WP_User_Query;

abstract class User_Iterator extends Iterator {

    protected $labels = [
        'single'    => 'user',
        'plural'    => 'users',
    ];

    public function get_default_query_vars () {

        return [
            'number'    => $this->batch_size,
            'offset'    => $this->index,
            'orderby'   => 'ID',
            'order'     => 'ASC',
        ];

    }

    public function get_query_vars ($vars) {

        return $vars;

    }

    public function get_items () {

        $query = new WP_User_Query($this->get_query_vars($this->get_default_query_vars()));

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