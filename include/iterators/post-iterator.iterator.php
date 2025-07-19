<?php

namespace Digitalis;

use \WP_Query;

abstract class Post_Iterator extends Iterator {

    protected $post_type   = 'post';
    protected $post_status = 'any';

    protected $labels = [
        'single'    => 'post',
        'plural'    => 'posts',
    ];

    public function get_query_vars ($vars) {

        return $vars;

    }

    public function process_post ($wp_post) {}

    //

    public function get_default_query_vars () {

        return [
            'post_type'      => $this->post_type,
            'post_status'    => $this->post_status,
            'posts_per_page' => $this->batch_size,
            'offset'         => $this->index,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ];

    }

    public function get_items () {

        $query = new WP_Query($this->get_query_vars($this->get_default_query_vars()));

        return $query->get_posts();

    }

    public function get_total_items () {

        $args = wp_parse_args([
            'posts_per_page' => -1,
            'offset'         => false,
            'fields'         => 'ids',
        ], $this->get_query_vars($this->get_default_query_vars()));

        return (new WP_Query($args))->found_posts;

    }

    public function get_item_id ($item) {

        return $item->ID;

    }

    public function process_item ($item) {

        return static::inject([$this, 'process_post'], [$item, $item]);

    }

}