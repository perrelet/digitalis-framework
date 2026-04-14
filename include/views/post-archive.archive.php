<?php

namespace Digitalis;

use WP_Query;

class Post_Archive extends Archive {

    protected static $defaults = [
        'no_items'   => 'No posts found.',
        'item_model' => Post::class,
    ];

    public function get_page_links ($wp_query) {

        if (!($wp_query instanceof WP_Query) || !($wp_query->max_num_pages > 1)) return [];

        $args = wp_parse_args($this->paginate_args, [
            'current'   => max(1, $wp_query->get('paged')),
            'total'     => $wp_query->max_num_pages,
            'type'      => 'array',
        ]);

        if ((wp_doing_ajax() || defined('REST_REQUEST')) && ($referer = wp_get_referer())) {

            $parts          = parse_url($referer);
            $path           = preg_replace('#/page/\d+/?#', '/', $parts['path'] ?? '/');
            $params         = array_diff_key($_POST, array_flip(['action', 'nonce', 'paged']));
            $query          = http_build_query($params);
            $args['base']   = trailingslashit($path) . '%_%';
            $args['format'] = 'page/%#%/' . ($query ? "?{$query}" : '');
            $args['add_args'] = [];

        }

        return paginate_links($args);

    }

}