<?php

namespace Digitalis\Field;

class Input extends \Digitalis\Field {

    protected static $defaults = [
        'type'      => 'text',
        'maxlength' => null,
        'minlength' => null,
    ];

    public function params (&$p) {
    
        if (!is_null($p['maxlength'])) $p['attributes']['maxlength'] = $p['maxlength'];
        if (!is_null($p['minlength'])) $p['attributes']['minlength'] = $p['minlength'];

        if ($p['type']) $p['attributes']['type'] = $p['type'];
    
        parent::params($p);

        $p['element']->set_tag('input');
        $p['element']->set_attribute($p['once_atts']);
    
    }

}