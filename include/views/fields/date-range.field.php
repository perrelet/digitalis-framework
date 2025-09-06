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

    public function params (&$p) {

        parent::params($p);

        if (!$p['name_start']) $p['name_start'] = $p['name'] . '-start';
        if (!$p['name_end'])   $p['name_end']   = $p['name'] . '-end';

        $p['value_start'] = is_null($p['value_start']) ? $this->query_value($p['name_start'], $p['default_start']) : $p['value_start'];
        $p['value_end']   = is_null($p['value_end'])   ? $this->query_value($p['name_end'],   $p['default_end'])   : $p['value_end'];

        if (isset($p['element']['value'])) unset($p['element']['value']);
        if (isset($p['element']['id']))    unset($p['element']['id']);
        if (isset($p['element']['name']))  unset($p['element']['name']);

    }

    public function after () {

        $json = json_encode($this['date_picker']);

        echo "<script>new DateRangePicker(document.getElementById('{$this['id']}'), {$json});</script>";

        parent::after();

    }

}