<?php

namespace Digitalis\Field;

use Digitalis\Attributes;

class Select extends \Digitalis\Field {

    protected static $template = 'select';

    protected static $defaults = [
    ];

    public static function params ($p) {
        
        $p = parent::params($p);

        $p['option_atts'] = static::get_option_attributes($p);

        $p['element']->set_tag('select');
        $p['element']->set_attribute($p['once_atts']);

        return $p;

    }

    protected static function get_option_attributes ($p) {
    
        if ($p['options']) foreach ($p['options'] as $option => $label) {

            if (is_array($label)) {

                if ($label) foreach ($label as $sub_option => $sub_option_label) {

                    if (!isset($p['option_atts'][$sub_option])) $p['option_atts'][$sub_option] = new Attributes();
                    if ($selected = static::selected($sub_option, $p['value'])) $p['option_atts'][$sub_option] += $selected;

                }
                

            } else {

                if (!isset($p['option_atts'][$option])) $p['option_atts'][$option] = new Attributes();
                if ($selected = static::selected($option, $p['value'])) $p['option_atts'][$option]['selected'] = 'selected';

            }

        }

        return $p['option_atts'];
    
    }

}