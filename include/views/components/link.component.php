<?php

namespace Digitalis\Component;

abstract class Link extends \Digitalis\Component {

    protected static $defaults = [
        'tag'  => 'a',
        'href' => '#',
    ];

}
