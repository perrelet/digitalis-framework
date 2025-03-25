<?php

namespace Digitalis\Field;

class Range extends Input {

    protected static $defaults = [
        'type'          => 'range',
        'min'           => 0,
        'max'           => 1,
        'step'          => 0.01,
        'show_value'    => true,
        'value_prefix'  => '',
        'value_suffix'  => '',
    ];

    public function params (&$p) {

        $p['attributes']['min']  = $p['min'];
        $p['attributes']['max']  = $p['max'];
        $p['attributes']['step'] = $p['step'];
        
        if ($p['show_value']) {

            $p['classes'][] = "has-output";
            $p['attributes']['oninput'] = "this.parentElement.parentElement.querySelector(`[name=\"{$p['key']}_output\"]`).value=`{$p['value_prefix']}`+this.value+`{$p['value_suffix']}`";

        }

        parent::params($p);

    }

    public function after () {
        
        if ($this['show_value']) {

            $output = $this['value_prefix'] . $this['value'] . $this['value_suffix'];
            echo "<output name='{$this['key']}_output' for='{$this['id']}'>{$output}</output>";

        }

        parent::after();

    }

}