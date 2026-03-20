<?php

namespace Digitalis\Field;

class Password extends Input {

    protected static $template = 'password';

    protected static $defaults = [
        'type'        => 'password',
        'button'      => true,
        'show_label'  => 'Show',
        'hide_label'  => 'Hide',
        'show_tag'    => 'button',
        'show_attr'   => [
            'onclick' => 'let pw = this.previousElementSibling; pw.type = (pw.type==`password` ? `text` : `password`); this.innerHTML = (pw.type==`password` ? this.dataset.show : this.dataset.hide);',
            'type'    => 'button',
        ],
    ];

    protected static $elements = ['show'];

    public function params (&$p) {
    
        if ($p['button']) {

            $p['show_attr']['data-show'] = $p['show_label'];
            $p['show_attr']['data-hide'] = $p['hide_label'];
            $p['show_content'] = $p['show_label'];

        }

        parent::params($p);

    }

}