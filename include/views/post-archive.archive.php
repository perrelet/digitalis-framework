<?php

namespace Digitalis;

use WP_Query;

class Post_Archive extends Archive {

    protected static $defaults = [
        'no_items'   => 'No posts found.',
        'item_model' => Post::class,
    ];

    public function get_page_links ($query) {

        if (!($query instanceof WP_Query) || !($query->max_num_pages > 1)) return [];
    
        return paginate_links(wp_parse_args($this['paginate_args'], [
            'current'   => max(1, $query->get('paged')),
            'total'     => $query->max_num_pages,
            'type'      => 'array',
        ]));
    
    }

}