<?php

namespace Digitalis\Component;

class HTMX extends \Digitalis\Component {

    protected static $defaults = [
        'tag'           => 'a',
        'attributes'    => [
            'href' => '#',
        ],
        'content'    => '',
        'url'        => null,
        'method'     => 'get',
        'trigger'    => 'click',
        'target'     => 'body',
        'swap'       => 'innerHTML',
        'swap_oob'   => null,
        'select'     => null,
        'select_oob' => null,
        'vals'       => null,
        'push_url'   => null,
        'confirm'    => null,
        '_'          => null,
    ];

    public function params (&$p) {

        if ($p['url'])        $p['attributes']['hx-' . $p['method']] = $p['url'];
        if ($p['trigger'])    $p['attributes']['hx-trigger']         = $p['trigger'];
        if ($p['target'])     $p['attributes']['hx-target']          = $p['target'];
        if ($p['swap'])       $p['attributes']['hx-swap']            = $p['swap'];
        if ($p['swap_oob'])   $p['attributes']['hx-swap-oob']        = $p['swap_oob'];
        if ($p['select'])     $p['attributes']['hx-select']          = $p['select'];
        if ($p['select_oob']) $p['attributes']['hx-select-oob']      = $p['select_oob'];
        if ($p['vals'])       $p['attributes']['hx-vals']            = $p['vals'];
        if ($p['push_url'])   $p['attributes']['hx-push-url']        = $p['push_url'];
        if ($p['confirm'])    $p['attributes']['hx-confirm']         = $p['confirm'];
        if ($p['_'])          $p['attributes']['_']                  = $p['_'];

        parent::params($p);
    
    }

}