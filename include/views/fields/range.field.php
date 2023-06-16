<?php

namespace Digitalis\Field;

use Digitalis\Field;

class Range extends Field {

    protected static $defaults = [
        'type'          => 'range',
        'min'           => 0,
        'max'           => 1,
        'step'          => 0.01,
        'show_value'    => true,
        'value_prefix'  => '',
        'value_suffix'  => '',
    ];

    public static function params ($p) {

        //dprint($p['attributes']);

        $p['attributes']['min'] = $p['min'];
        $p['attributes']['max'] = $p['max'];
        $p['attributes']['step'] = $p['step'];
        
        if ($p['show_value']) {

            $p['classes'][] = "has-output";
            $p['attributes']['oninput'] = "{$p['key']}_output.value=`{$p['value_prefix']}`+this.value+`{$p['value_suffix']}`";

        }

        $p = parent::params($p);

        return $p;
        
    }

    public static function after ($p) {
        
        if ($p['show_value']) {

            $output = $p['value_prefix'] . $p['value'] . $p['value_suffix'];
            echo "<output name='{$p['key']}_output' for='{$p['id']}'>{$output}</output>";

        }

        parent::after($p);
        
    }

}