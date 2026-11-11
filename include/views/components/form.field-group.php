<?php

namespace Digitalis;

class Form extends Field_Group {

    protected static $defaults = [
        'tag'         => 'form',
        'method'      => null,
        'action'      => null,
        'unavailable' => '',
    ];

    public function params (&$p) {

        $p = apply_filters('Digitalis/Form/Params', $p, $this);

        if ($p['method']) $p['attributes']['method'] = $p['method'];
        if ($p['action']) $p['attributes']['action'] = $p['action'];

        // docs/FORMS.md, "When a route cannot serve".
        if ($p['unavailable']) $p = ['tag' => 'div', 'attributes' => [], 'fields' => [new Component(['tag' => 'p', 'classes' => ['form-unavailable'], 'content' => esc_html($p['unavailable'])])]] + $p;

        parent::params($p);

    }

    public function condition () {

        return !$this['unavailable'] || current_user_can('edit_posts');

    }

}