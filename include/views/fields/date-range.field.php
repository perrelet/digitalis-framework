<?php

namespace Digitalis\Field;

class Date_Range extends Date_Picker {

    protected static $template = 'date-range';

    protected static $defaults = [
        'value_start'   => null,
        'value_end'     => null,
        'default_start' => '',
        'default_end'   => '',
        'name_start'    => null,
        'name_end'      => null,
        'seperator'     => '-',
    ];

    public static function params ($p) {

        $p = parent::params($p);

        if (!$p['name_start']) $p['name_start'] = $p['name'] . '-start';
        if (!$p['name_end'])   $p['name_end']   = $p['name'] . '-end';

        $p['value_start'] = is_null($p['value_start']) ? static::query_value($p['name_start'], $p['default_start']) : $p['value_start'];
        $p['value_end']   = is_null($p['value_end'])   ? static::query_value($p['name_end'],   $p['default_end'])   : $p['value_end'];

        if (isset($p['element']['value'])) unset($p['element']['value']);
        if (isset($p['element']['id']))    unset($p['element']['id']);
        if (isset($p['element']['name']))  unset($p['element']['name']);

        return $p;

    }

    public static function after ($p) {

        $json = json_encode($p['date-picker']);

        echo "<script>new DateRangePicker(document.getElementById('{$p['id']}'), {$json});</script>";

        parent::after($p);

    }

}