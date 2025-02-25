<?php

namespace Digitalis\Field;

class Hidden extends Input {

    protected static $defaults = [
        'type' => 'hidden',
        'wrap' => false,
    ];

    public static function params ($p) {
    
        $p['wrap'] = false;
    
        return parent::params($p);
    
    }

}