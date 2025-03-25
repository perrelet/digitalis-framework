<?php

namespace Digitalis\Field;

class Checkbox extends Input {

    protected static $template = 'checkbox';

    protected static $defaults = [
        'type'          => 'checkbox',
        'null_value'    => 0,
        'checked_value' => 1,
    ];

    public function params (&$p) {

        parent::params($p);

        if ($checked = $this->checked($p['checked_value'], $p['value'])) $p['element']['checked'] = 'checked';

        $p['element']['value'] = $p['checked_value'];

    }

}