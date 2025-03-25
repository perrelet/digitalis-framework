<?php

namespace Digitalis\Field;

use Digitalis\Attributes;

class Select extends \Digitalis\Field {

    protected static $template = 'select';

    protected static $defaults = [
    ];

    public function params (&$p) {
        
        parent::params($p);

        $p['option_atts'] = $this->get_option_attributes($p['option_atts']);

        $p['element']->set_tag('select');
        $p['element']->set_attribute($p['once_atts']);

    }

    public function get_option_attributes ($atts = []) {
    
        foreach ($this['options'] as $option => $label) {

            if (is_array($label)) {

                if ($label) foreach ($label as $sub_option => $sub_option_label) {

                    if (!isset($atts[$sub_option])) $atts[$sub_option] = new Attributes();
                    if ($selected = $this->selected($sub_option, $this['value'])) $atts[$sub_option]['selected'] = 'selected';

                }

            } else {

                if (!isset($atts[$option])) $atts[$option] = new Attributes();
                if ($selected = $this->selected($option, $this['value'])) $atts[$option]['selected'] = 'selected';

            }

        }

        return $atts;

    }

}