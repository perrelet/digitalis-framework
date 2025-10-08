<?php

namespace Digitalis\Field;

class Textarea extends \Digitalis\Field {

    protected static $template      = 'textarea';
    protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "/templates/digitalis/fields/";

    protected static $defaults = [
        'rows'       => null,
        'cols'       => null,
        'spellcheck' => null,
    ];

    public function params (&$p) {
    
        if (!is_null($p['rows']))       $p['attributes']['rows']       = $p['rows'];
        if (!is_null($p['cols']))       $p['attributes']['cols']       = $p['cols'];
        if (!is_null($p['spellcheck'])) $p['attributes']['spellcheck'] = $p['spellcheck'] ? 'true' : 'false';
    
        parent::params($p);
    
        $p['element']->set_tag('textarea');
        $p['element']->set_content($p['value']);

        if (isset($p['element']['value'])) unset($p['element']['value']);

    }

}