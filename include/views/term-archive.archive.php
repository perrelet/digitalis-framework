<?php

namespace Digitalis;

abstract class Term_Archive extends Archive {

    protected static $defaults = [
        'id'         => 'digitalis-term-archive',
        'classes'    => ['digitalis-term-archive'],
        'no_items'   => 'No terms found.',
        'pagination' => false,
        'query_vars' => [],
        'item_model' => Term::class,
    ];

    public function get_page_links ($query) {

        // TODO: WP_Term_Query doesn't appear to easily support pagination.
    
    }

}