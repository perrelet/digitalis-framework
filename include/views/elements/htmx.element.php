<?php

namespace Digitalis\Element;

use Digitalis\Element;

class HTMX extends Element {

    protected static $defaults = [
        'tag'           => 'a',
        'attributes'    => [
            'href' => '#',
        ],
        'content' => '',
        'url'     => null,
        'method'  => 'get',
        'trigger' => 'click',
        'target'  => 'body',
        'swap'    => 'innerHTML',
        'confirm' => false,
        '_'       => false,
    ];

    public static function get_attributes ($p, $prefix = '') {
    
        $p = parent::get_attributes($p, $prefix);

        if ($p['url'])     $p['attributes']['hx-' . $p['method']] = $p['url'];
        if ($p['trigger']) $p['attributes']['hx-trigger']         = $p['trigger'];
        if ($p['target'])  $p['attributes']['hx-target']          = $p['target'];
        if ($p['swap'])    $p['attributes']['hx-swap']            = $p['swap'];
        if ($p['confirm']) $p['attributes']['hx-confirm']         = $p['confirm'];
        if ($p['_'])       $p['attributes']['_']                  = $p['_'];

        return $p;
    
    }

}