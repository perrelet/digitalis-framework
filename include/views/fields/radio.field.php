<?php

namespace Digitalis\Field;

use Digitalis\Attributes;

class Radio extends Input {

    protected static $template = 'radio';

    protected static $defaults = [
        'type'        => 'radio',
        'options'     => [],
        'option_atts' => [],
    ];

    public static function params ($p) {
        
        $p = parent::params($p);

        $p['option_atts'] = static::get_option_attributes($p);

        return $p;

    }

    protected static function get_option_attributes ($p) {
    
        if ($p['options']) foreach ($p['options'] as $option => $label) {

            if (!isset($p['option_atts'][$option])) $p['option_atts'][$option] = new Attributes();

            if ($checked = static::checked($option, $p['value'])) $p['option_atts'][$option]['checked'] = 'checked';

        }

        return $p['option_atts'];
    
    }

}