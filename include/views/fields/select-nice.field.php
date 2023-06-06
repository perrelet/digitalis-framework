<?php

namespace Digitalis\Field;

class Select_Nice extends Select {

    protected static $defaults = [
        'nice-select'   => [],
        'classes'       => ['field-nice-select'],
    ];

    public static function params ($p) {

        $js_var = str_replace("-", "_", $p['key']) . "_nice";

        $p['js_var'] = $js_var;
        $p['attributes']['data-js-var'] = $js_var;
 
        $p['nice-select'] = wp_parse_args($p['nice-select'], [
            'searchable'    => true,
            'placeholder'   => 'select',
        ]);

        $p = parent::params($p);

        return $p;

    }

    public static function before_first ($p) {

        echo '<link href="https://cdn.jsdelivr.net/npm/nice-select2@2.1.0/dist/css/nice-select2.min.css" rel="stylesheet">';
        echo '<script src="https://cdn.jsdelivr.net/npm/nice-select2@2.1.0/dist/js/nice-select2.min.js"></script>';

        parent::before_first($p);

    }

    public static function after_first ($p) {

        echo "<script>let nice_selects = {};</script>";

    }

    public static function after ($p) {

        $json = json_encode($p['nice-select']);
        echo "<script>nice_selects.{$p['js_var']} = NiceSelect.bind(document.getElementById('{$p['id']}'), {$json});</script>";

        parent::after($p);

    }

}