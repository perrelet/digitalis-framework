<?php

namespace Digitalis\Field;

class Select_Nice extends Select {

    protected static $defaults = [
        'nice-select'  => [],
        'classes'      => ['field-nice-select'],
        'load_scripts' => true,
        'load_styles'  => true,
    ];

    public static function params ($p) {

        $js_var = str_replace("-", "_", $p['key']) . "_nice";

        $p['js_var'] = $js_var;
        $p['attributes']['data-js-var'] = $js_var;
 
        $p['nice-select'] = wp_parse_args($p['nice-select'], [
            'searchable'    => true,
            'placeholder'   => 'Select',
        ]);

        $p = parent::params($p);

        return $p;

    }

    public static function load_styles () {
    
        echo '<link href="https://cdn.jsdelivr.net/npm/nice-select2@2.2.0/dist/css/nice-select2.min.css" rel="stylesheet">';
    
    }

    public static function load_scripts () {
    
        echo '<script src="https://cdn.jsdelivr.net/npm/nice-select2@2.2.0/dist/js/nice-select2.min.js"></script>';
    
    }

    protected static function before_first ($p) {

        if ($p['load_styles'])  static::load_styles();
        if ($p['load_scripts']) static::load_scripts();

        parent::before_first($p);

    }

    protected static function after_first ($p) {

        echo "<script>nice_selects = typeof(nice_selects) == 'undefined' ? {} : nice_selects;</script>";

    }

    protected static function after ($p) {

        $json = json_encode($p['nice-select']);
        echo "<script>nice_selects.{$p['js_var']} = NiceSelect.bind(document.getElementById('{$p['id']}'), {$json});</script>";

        parent::after($p);

    }

}