<?php

namespace Digitalis\Field;

class Hidden extends Input {

    protected static $defaults = [
        'type' => 'hidden',
        'wrap' => false,
    ];

    public function params (&$p) {
    
        $p['wrap'] = false;
    
        parent::params($p);
    
    }

}