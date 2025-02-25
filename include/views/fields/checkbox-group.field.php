<?php

namespace Digitalis\Field;

use Digitalis\Field;
use Digitalis\Attributes;

class Checkbox_Group extends Input {

    protected static $template = 'checkbox-group';

    protected static $defaults = [
        'type'           => 'checkbox',
        'options'        => [],
        'option_atts'    => [],
        'select_all'     => false,
        'select_all_key' => 'all',
        'null_value'     => 0,
    ];

    public static function params ($p) {

        if ($p['select_all']) {

            $select_all_label = is_string($p['select_all']) ? $p['select_all'] : 'Select All';

            $p['options'] = [$p['select_all_key'] => $select_all_label] + $p['options'];

        }

        $p = parent::params($p);

        $p['option_atts'] = static::get_option_attributes($p);

        if (isset($p['element']['value'])) unset($p['element']['value']);
        if (isset($p['element']['id']))    unset($p['element']['id']);
        if (isset($p['element']['name']))  $p['element']['name'] .= "[]";

        return $p;

    }

    protected static function get_value ($p) {

        $value = parent::get_value($p);
        if (!is_array($value)) $value = explode(',', $value);
        return (array) $value;
        
    }

    protected static function get_option_attributes ($p) {
    
        if ($p['options']) foreach ($p['options'] as $option => $label) {

            if (!isset($p['option_atts'][$option])) $p['option_atts'][$option] = new Attributes();

            //$p['option_atts'][$option]['id']    = $p['id'] . '-' . $option;
            $p['option_atts'][$option]['value'] = $option;

            if ($option == $p['select_all_key']) {

                $p['option_atts'][$option]['onchange'] = "this.parentElement.parentElement.querySelectorAll(`[name=\"` + this.name + `\"]`).forEach((f, i) => { f.checked = this.checked });";

            }

            if ($checked = static::checked($option, $p['value'])) $p['option_atts'][$option]['checked'] = 'checked';

        }

        return $p['option_atts'];
    
    }

}