<?php

namespace Digitalis\Field;

class Hidden_Group extends \Digitalis\Field_Group {

    protected static $defaults = [
        'data'    => [],
        'classes' => ['hidden-group'],
    ];

    public static function params ($p) {
    
        if ($p['data']) foreach ($p['data'] as $key => $value) {
        
            $p['fields'][] = [
                'field' => Hidden::class,
                'key'   => $key,
                'value' => $value,
            ];
        
        }

        $p = parent::params($p);

        return $p;

    }

}