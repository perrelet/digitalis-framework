<?php

namespace Digitalis\Field;

class Date_Picker extends Input {

    protected static $defaults = [
        'type'          => 'text',
        'date-picker'   => [],
        'classes'       => ['field-date-picker'],
    ];

    public static function params ($p) {

        $p['date-picker'] = wp_parse_args($p['date-picker'], [
            'autohide'  => false,
            'format'    => "dd/mm/yyyy",
        ]);

        $p = parent::params($p);

        return $p;

    }

    public static function before_first ($p) {

        echo '<link href="https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/dist/css/datepicker.min.css" rel="stylesheet">';
        echo '<script src="https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/dist/js/datepicker-full.min.js"></script>';

        parent::before_first($p);

    }

    public static function after ($p) {

        $json = json_encode($p['date-picker']);

        if (strpos(static::class, 'Date_Range') === false) echo "<script>new Datepicker(document.getElementById('{$p['id']}'), {$json});</script>";

        parent::after($p);

    }

}