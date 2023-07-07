<?php

namespace Digitalis\Field;

use Digitalis\Field;

class Button extends Field {

    protected static $template = 'button';

    protected static $defaults = [
        'key'  => false,
        'type' => 'button',
        'text' => 'Button',
    ];

}