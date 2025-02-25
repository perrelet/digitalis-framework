<?php

namespace Digitalis\Field;

class Checkbox extends Input {

    protected static $template = 'checkbox';

    protected static $defaults = [
        'type'       => 'checkbox',
        'null_value' => 0,
    ];

    public static function params ($p) {
    
        $p = parent::params($p);

        if ($p['value']) $p['element']->set_attribute('checked', 'checked');

        return $p;
    
    }

}