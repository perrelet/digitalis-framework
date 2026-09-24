<?php

namespace Lattice\Field;

class Date_Picker extends Input {

    protected static $defaults = [
        'type'          => 'text',
        'date_picker'   => [],
        'classes'       => ['field-date-picker'],
    ];

    public function params (&$p) {

        $p['date_picker'] = wp_parse_args($p['date_picker'], [
            'autohide'  => false,
            'format'    => "dd/mm/yyyy",
        ]);

        parent::params($p);

    }

    public function before_first () {

        echo '<link href="https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/dist/css/datepicker.min.css" rel="stylesheet">';
        echo '<script src="https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/dist/js/datepicker-full.min.js"></script>';

        parent::before_first();

    }

    public function after () {

        $id   = wp_json_encode($this['id'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $json = wp_json_encode($this['date_picker'], JSON_HEX_TAG | JSON_HEX_AMP);

        if (strpos(static::class, 'Date_Range') === false) echo "<script>new Datepicker(document.getElementById({$id}), {$json});</script>";

        parent::after();

    }

}