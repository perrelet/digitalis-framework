<?php

namespace Digitalis;

class HTMX_Element extends Element {

    protected static $template = 'element';

    protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "/templates/digitalis/";

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
        '_'       => false,
    ];

    public static function get_attributes ($p) {
    
        $p = parent::get_attributes($p);

        if ($p['url'])     $p['attributes']['hx-' . $p['method']] = $p['url'];
        if ($p['trigger']) $p['attributes']['hx-trigger']         = $p['trigger'];
        if ($p['target'])  $p['attributes']['hx-target']          = $p['target'];
        if ($p['swap'])    $p['attributes']['hx-swap']            = $p['swap'];
        if ($p['_'])       $p['attributes']['_']                  = $p['_'];

        return $p;
    
    }

}