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

    public function params (&$p) {
        
        parent::params($p);

        $p['option_atts'] = $this->get_option_attributes($p['option_atts']);

        if (isset($p['element']['value'])) unset($p['element']['value']);
        if (isset($p['element']['id']))    unset($p['element']['id']);

    }

    public function get_option_attributes ($atts = []) {

        foreach ($this['options'] as $option => $label) {

            if (!isset($atts[$option])) $atts[$option] = new Attributes();

            $atts[$option]['value'] = $option;

            if ($checked = $this->checked($option, $this['value'])) $atts[$option]['checked'] = 'checked';

        }

        return $atts;

    }

}