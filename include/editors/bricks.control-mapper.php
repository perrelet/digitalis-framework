<?php

namespace Digitalis;

class Bricks_Control_Mapper extends Control_Mapper {

    public function map_control (array $control) : array {

        return [
            'type'    => $control['type']    ?? 'text',
            'name'    => $control['name']    ?? '',
            'label'   => $control['label']   ?? $this->generate_label_from_name($name),
            'default' => $control['default'] ?? '',
        ];

    }

}