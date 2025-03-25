<?php

namespace Digitalis;

class Form extends Field_Group {

    protected static $defaults = [
        'tag'    => 'form',
        'method' => null,
        'action' => null,
    ];

    public function params (&$p) {
    
        if ($p['method']) $p['attributes']['method'] = $p['method'];
        if ($p['action']) $p['attributes']['action'] = $p['action'];
    
        parent::params($p);
    
    }

}