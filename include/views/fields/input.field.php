<?php

namespace Digitalis\Field;

use Digitalis\Field;

class Input extends Field {

    protected static $defaults = [
        'maxlength' => null,
        'minlength' => null,
    ];

    public static function params ($p) {
    
        if (!is_null($p['maxlength'])) $p['attributes']['maxlength'] = $p['maxlength'];
        if (!is_null($p['minlength'])) $p['attributes']['minlength'] = $p['minlength'];
    
        return parent::params($p);
    
    }

}