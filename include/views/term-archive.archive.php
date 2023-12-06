<?php

namespace Digitalis;

abstract class Term_Archive extends Archive {

    protected static $params = []; // Because this view invokes another view, we need this in order to correctly LSB.

    protected static $defaults = [
        'id'            => 'digitalis-term-archive',
        'no_posts'      => 'No terms found.',
        'pagination'    => false,
        'query_vars'    => [],
    ];

    protected static function get_items ($query_vars, &$query, $skip_main) {

        return get_terms($query_vars);

    }

    public static function get_classes ($p) {

        $p['classes'] = parent::get_classes($p);
        $p['classes'][] = 'digitalis-term-archive';
        return $p['classes'];

    }

}