<?php

namespace Digitalis\Field;

use Digitalis\Field;

class Checkbox_Group extends Field {

    protected static $template = 'checkbox-group';

    protected static $defaults = [
        'type'          => 'checkbox-group',
        'select_all'    => false,
    ];

    public static function params ($p) {
        
        $p = parent::params($p);

        if ($p['select_all']) {

            $p['options'] = array_merge([
                'all' => is_string($p['select_all']) ? $p['select_all'] : 'Select All',
            ], $p['options']);

        }

        return $p;
        
    }

}