<?php

namespace Digitalis\Field;

class Date_Picker extends Input {

    protected static $defaults = [
        'type'          => 'text',
        'date-picker'   => [],
        'classes'       => ['field-date-picker'],
    ];

    public function params (&$p) {

        $p['date-picker'] = wp_parse_args($p['date-picker'], [
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

        $json = json_encode($this['date-picker']);

        if (strpos(static::class, 'Date_Range') === false) echo "<script>new Datepicker(document.getElementById('{$this['id']}'), {$json});</script>";

        parent::after();

    }

}