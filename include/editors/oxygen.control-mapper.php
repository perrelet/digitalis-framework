<?php

namespace Digitalis;

class Oxygen_Control_Mapper extends Control_Mapper {

    public function map_group (array $group) : array {

        return [
            'name'    => $group['name']  ?? '',
            'label'   => $this->get_value($group, 'label', fn () => $this->generate_label_from_name($group['name']  ?? '')),
            'icon'    => $this->get_value($group, 'icon'),
        ];

    }

    public function map_control (array $control) : array {

        $name = $this->get_value($control, 'name', '');

        return [
            'name'    => $control['name']  ?? '',
            'type'    => $this->map_type($control['type'] ?? 'text'),
            'label'   => $this->get_value($control, 'label',   fn () => $this->generate_label_from_name($control['name']  ?? '')),
            'default' => $this->get_value($control, 'default', ''),
            'rebuild' => $this->get_value($control, 'live',    false),
        ];

    }

    /* protected function translate_condition ($condition) {

        if ($)

    } */

    protected function map_type (string $type) : string {

        return match($type) {
            'text'     => 'textfield',
            'textarea' => 'textarea',
            'select'   => 'dropdown',
            'radio'    => 'radio',
            'checkbox' => 'checkbox',
            'color'    => 'colorpicker',
            'number'   => 'slider',
            //''         => 'measurebox',
            //''         => 'slider-measurebox',
            'url'      => 'hyperlink',
            //'file'   => 'mediaurl',
            
            default    => 'textfield',
        };

    }

}