<?php

namespace Digitalis\Field;

use Digitalis\Field;

class Checkbox_Group extends Field {

    protected static $template = 'checkbox-group';

    protected static $defaults = [
        'type'           => 'checkbox-group',
        'options'        => [],
        'select_all'     => false,
        'select_all_key' => 'all',
        'option_atts'    => [],
    ];

    public static function params ($p) {
        
        $p = parent::params($p);

        if ($p['select_all']) {

            $select_all_label = is_string($p['select_all']) ? $p['select_all'] : 'Select All';

            $p['options'] = [$p['select_all_key'] => $select_all_label] + $p['options'];

        }

        if($p['value']) $p['value'] = explode(',', $p['value']);

        $p['option_atts'] = static::get_option_attributes($p);
        $p['option_atts'] = static::generate_option_attributes($p);

        return $p;

    }

    protected static function get_option_attributes ($p) {
    
        if ($p['options']) foreach ($p['options'] as $option => $label) {

            if (!isset($p['option_atts'][$option])) $p['option_atts'][$option] = [];

            if ($option == $p['select_all_key']) {

                $p['option_atts'][$option]['onchange'] = "this.parentElement.parentElement.querySelectorAll(`[name=` + this.name + `]`).forEach((f) => f.checked = this.checked)";

            }

            if ($checked = static::checked($option, $p['value'])) $p['option_atts'][$option] += $checked;

        }

        return $p['option_atts'];
    
    }

}