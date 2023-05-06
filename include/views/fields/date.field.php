<?php

namespace Digitalis\Field;

use Digitalis\Field;

class Date extends Field {

    protected static $template = 'input';

    protected static $defaults = [
        'type' => 'date',
    ];

}