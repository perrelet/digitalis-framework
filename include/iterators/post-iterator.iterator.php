<?php

declare(strict_types=1);

namespace Lattice;

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
            'posts_per_page' => 1, // found_posts is the total; one row is fetched instead of every id
            'offset'         => false,
            'fields'         => 'ids',
            'no_found_rows'  => false, // a subclass's no_found_rows => true answered 0 and the iterator never completed
        ], $this->get_query_vars($this->get_default_query_vars()));

        return (new WP_Query($args))->found_posts;

    }

    // The id keys are applied last, so a subclass's get_query_vars() still shapes the rest of the query.
    public function get_items_by_id (array $ids) {

        if (!$ids) return [];

        $vars = ['post__in' => $ids, 'posts_per_page' => count($ids), 'offset' => 0, 'ignore_sticky_posts' => true] + $this->get_query_vars($this->get_default_query_vars());

        return (new WP_Query($vars))->get_posts();

    }

    public function get_item_url ($id) {

        $url = get_edit_post_link((int) $id, 'raw');

        return is_string($url) && $url !== '' ? $url : null;

    }

    public function get_item_id ($item) {

        return $item->ID;

    }

    public function process_item ($item) {

        return static::inject([$this, 'process_post'], [$item, $item]);

    }

}