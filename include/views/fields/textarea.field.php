<?php

namespace Digitalis\Field;

class Textarea extends \Digitalis\Field {

    protected static $template      = 'textarea';
    protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "/templates/digitalis/fields/";

    protected static $defaults = [
        'type'       => false,
        'rows'       => null,
        'cols'       => null,
        'spellcheck' => null,
    ];

    public static function params ($p) {
    
        if (!is_null($p['rows']))       $p['attributes']['rows']       = $p['rows'];
        if (!is_null($p['cols']))       $p['attributes']['cols']       = $p['cols'];
        if (!is_null($p['spellcheck'])) $p['attributes']['spellcheck'] = $p['spellcheck'] ? 'true' : 'false';
    
        return parent::params($p);
    
    }

}