<?php

namespace Digitalis\Field;

class Number extends \Digitalis\Field {

    protected static $template = 'input';

    protected static $defaults = [
        'type'  => 'number',
        'min'   => null,
        'max'   => null,
        'step'  => null,
    ];

    public static function params ($p) {

        if (!is_null($p['min']))  $p['attributes']['min'] = $p['min'];
        if (!is_null($p['max']))  $p['attributes']['max'] = $p['max'];
        if (!is_null($p['step'])) $p['attributes']['step'] = $p['step'];

        $p = parent::params($p);

        return $p;

    }

}