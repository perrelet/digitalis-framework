<?php

namespace Digitalis\Field;

use Digitalis\Field;

class Date_Range extends Date_Picker {

    protected static $template = 'date-range';

    protected static $defaults = [
        'seperator' => '-',
    ];

    public static function after ($p) {

        $json = json_encode($p['date-picker']);

        echo "<script>new DateRangePicker(document.getElementById('{$p['id']}'), {$json});</script>";

        parent::after($p);

    }

}