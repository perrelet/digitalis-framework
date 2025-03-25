<?php

namespace Digitalis\Field;

class Hidden_Group extends \Digitalis\Field_Group {

    protected static $defaults = [
        'data'    => [],
        'classes' => ['hidden-group'],
    ];

    public function params (&$p) {
    
        if ($p['data']) foreach ($p['data'] as $name => $value) {

            $p['fields'][] = new Hidden([
                'name'  => $name,
                'value' => $value,
            ]);

        }

        parent::params($p);

    }

}