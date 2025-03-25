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

    public function params (&$p) {

        if ($p['select_all']) {

            $select_all_label = is_string($p['select_all']) ? $p['select_all'] : 'Select All';

            $p['options'] = [$p['select_all_key'] => $select_all_label] + $p['options'];

        }

        parent::params($p);

        $p['option_atts'] = $this->get_option_attributes($p['option_atts']);

        if (isset($p['element']['value'])) unset($p['element']['value']);
        if (isset($p['element']['id']))    unset($p['element']['id']);
        if (isset($p['element']['name']))  $p['element']['name'] .= "[]";

    }

    public function get_value () {

        $value = parent::get_value();
        if (!is_array($value)) $value = explode(',', $value);
        return (array) $value;
        
    }

    protected function get_option_attributes ($atts = []) {
    
        foreach ($this['options'] as $option => $label) {

            if (!isset($atts[$option])) $atts[$option] = new Attributes();

            //$atts[$option]['id']    = $this['id'] . '-' . $option;
            $atts[$option]['value'] = $option;

            if ($option == $this['select_all_key']) {

                $atts[$option]['onchange'] = "this.parentElement.parentElement.querySelectorAll(`[name=\"` + this.name + `\"]`).forEach((f, i) => { f.checked = this.checked });";

            }

            if ($checked = $this->checked($option, $this['value'])) $atts[$option]['checked'] = 'checked';

        }

        return $atts;

    }

}