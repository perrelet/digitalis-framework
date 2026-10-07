<?php

namespace Lattice\Field;

class Hidden extends Input {

    protected static $defaults = [
        'type' => 'hidden',
        'wrap' => false,
    ];

    public function params (array &$p) {
    
        $p['wrap'] = false;
    
        parent::params($p);
    
    }

}