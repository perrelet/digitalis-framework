<?php

namespace Digitalis\Element;

abstract class Table extends \Digitalis\Element {

    protected static $template = 'table';

    protected static $defaults = [
        'rows'          => [],
        'headers'       => [
            'first_row' => true,
            'first_col' => false,
        ],
        'attributes'    => [
            'role' => 'presentation',
        ],
    ];

    protected static $merge = ['headers'];

    protected static function condition ($p) {
        
        return is_array($p['rows']);
    
    }

}