<?php

namespace Digitalis\Field;

class Select_Nice extends Select {

    protected static $defaults = [
        'nice-select'  => [],
        'classes'      => ['field-nice-select'],
        'load_scripts' => true,
        'load_styles'  => true,
    ];

    public function params (&$p) {

        $js_var = str_replace("-", "_", $p['key']) . "_nice";

        $p['js_var'] = $js_var;
        $p['attributes']['data-js-var'] = $js_var;
 
        $p['nice-select'] = wp_parse_args($p['nice-select'], [
            'searchable'    => true,
            'placeholder'   => 'Select',
        ]);

        parent::params($p);

    }

    public function before_first () {

        if ($this['load_styles'])  $this->load_styles();
        if ($this['load_scripts']) $this->load_scripts();

        parent::before_first();

    }

    public function load_styles () {
    
        echo '<link href="https://cdn.jsdelivr.net/npm/nice-select2@2.2.0/dist/css/nice-select2.min.css" rel="stylesheet">';
    
    }

    public function load_scripts () {
    
        echo '<script src="https://cdn.jsdelivr.net/npm/nice-select2@2.2.0/dist/js/nice-select2.min.js"></script>';
    
    }

    public function after_first () {

        echo "<script>nice_selects = typeof(nice_selects) == 'undefined' ? {} : nice_selects;</script>";

    }

    public function after () {

        $json = json_encode($this['nice-select']);
        echo "<script>nice_selects.{$this['js_var']} = NiceSelect.bind(document.getElementById('{$this['id']}'), {$json});</script>";

        parent::after();

    }

}