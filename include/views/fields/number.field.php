<?php

namespace Digitalis\Field;

class Number extends Input {

    protected static $defaults = [
        'type'  => 'number',
        'min'   => null,
        'max'   => null,
        'step'  => null,
    ];

    public function params (&$p) {

        if (!is_null($p['min']))  $p['attributes']['min'] = $p['min'];
        if (!is_null($p['max']))  $p['attributes']['max'] = $p['max'];
        if (!is_null($p['step'])) $p['attributes']['step'] = $p['step'];

        parent::params($p);

    }

}