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
        'key_start'     => null,
        'key_end'       => null,
        'seperator'     => '-',
    ];

    public static function params ($p) {

        $p = parent::params($p);

        if (!$p['key_start']) $p['key_start'] = $p['key'] . '-start';
        if (!$p['key_end'])   $p['key_end']   = $p['key'] . '-end';

        $p['value_start'] = is_null($p['value_start']) ? static::query_value($p['key_start'], $p['default_start']) : $p['value_start'];
        $p['value_end']   = is_null($p['value_end'])   ? static::query_value($p['key_end'],   $p['default_end'])   : $p['value_end'];

        return $p;

    }

    public static function after ($p) {

        $json = json_encode($p['date-picker']);

        echo "<script>new DateRangePicker(document.getElementById('{$p['id']}'), {$json});</script>";

        parent::after($p);

    }

}