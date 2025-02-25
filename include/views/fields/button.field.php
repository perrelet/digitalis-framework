<?php

namespace Digitalis\Field;

class Button extends Input {

    protected static $defaults = [
        'type' => 'button',
        'text' => 'Button',
    ];

    public static function params ($p) {
    
        $p = parent::params($p);

        $p['element']->set_tag('button');
        $p['element']->set_attribute($p['once_atts']);
        $p['element']->set_content($p['text']);

        return $p;
    
    }

}