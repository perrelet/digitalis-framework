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
        'seperator'     => '&#8594;',
    ];

    protected static $elements = [
        'date_start',
        'date_end',
    ];

    public function params (&$p) {

        parent::params($p);

        if (!$p['name_start']) $p['name_start'] = $p['name'] . '-start';
        if (!$p['name_end'])   $p['name_end']   = $p['name'] . '-end';

        $p['value_start'] = is_null($p['value_start']) ? $this->query_value($p['name_start'], $p['default_start']) : $p['value_start'];
        $p['value_end']   = is_null($p['value_end'])   ? $this->query_value($p['name_end'],   $p['default_end'])   : $p['value_end'];

        $p['date_start']->set_tag('input');
        $p['date_start']->add_attrs($p['attributes']);
        $p['date_start']->set_id($p['id'] . '-start');
        $p['date_start']->name  = $p['name_start'];
        $p['date_start']->value = $p['value_start'];

        $p['date_end']->set_tag('input');
        $p['date_end']->add_attrs($p['pre_once_atts']);
        $p['date_end']->set_id($p['id'] . '-end');
        $p['date_end']->name  = $p['name_end'];
        $p['date_end']->value = $p['value_end'];

    }

    public function after () {

        $json = json_encode($this['date_picker']);

        echo "<script>new DateRangePicker(document.getElementById('{$this['id']}'), {$json});</script>";

        parent::after();

    }

}