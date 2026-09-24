<?php

namespace Lattice\Field;

class Select_Nice extends Select {

    protected static $defaults = [
        'nice-select'  => [],
        'classes'      => ['field-nice-select'],
        'load_scripts' => true,
        'load_styles'  => true,
    ];

    public function params (&$p) {

        $js_var = str_replace("-", "_", (string) ($p['name'] ?? $p['key'])) . "_nice";

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

        $js   = wp_json_encode($this['js_var'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $id   = wp_json_encode($this['id'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $json = wp_json_encode($this['nice-select'], JSON_HEX_TAG | JSON_HEX_AMP);

        echo "<script>nice_selects[{$js}] = NiceSelect.bind(document.getElementById({$id}), {$json});</script>";

        parent::after();

    }

}