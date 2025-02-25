<?php

namespace Digitalis\Field;

class File extends Input {

    protected static $defaults = [
        'type'     => 'file',
        'accept'   => [],
        'multiple' => false,
    ];

    public static function params ($p) {

        if ($p['accept']) $p['attributes']['accept'] = is_array($p['accept']) ? implode(', ', $p['accept']) : $p['accept'];

        if ($p['multiple']) $p['attributes']['multiple'] = 'multiple';
    
        return parent::params($p);
    
    }

}