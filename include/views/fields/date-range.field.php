<?php

namespace Digitalis\Field;

use Digitalis\Field;

class Date_Range extends Date_Picker {

    protected static $template = 'date-range';

    protected static $defaults = [
        'value_start'   => null,
        'value_end'     => null,
        'default_start' => '',
        'default_end'   => '',
        'seperator'     => '-',
    ];

    public static function params ($p) {

        $p = parent::params($p);

        $p['value_start'] = is_null($p['value_start']) ? ($_REQUEST[$p['key'] . '-start'] ?? $p['default_start']) : $p['value_start'];
        $p['value_end']   = is_null($p['value_end'])   ? ($_REQUEST[$p['key'] . '-start'] ?? $p['default_end'])   : $p['value_end'];

        return $p;

    }

    public static function after ($p) {

        $json = json_encode($p['date-picker']);

        echo "<script>new DateRangePicker(document.getElementById('{$p['id']}'), {$json});</script>";

        parent::after($p);

    }

}