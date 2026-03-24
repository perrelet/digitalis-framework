<?php

namespace Digitalis;

class Elementor_Control_Mapper extends Control_Mapper {

    public function map_control (array $control) : array {

        return [
            'type'    => $this->map_type($control['type'] ?? 'text'),
            'name'    => $control['name']    ?? '',
            'label'   => $control['label']   ?? $this->generate_label_from_name($name),
            'default' => $control['default'] ?? '',
        ];

    }

    protected function map_type (string $type) : string {

        return match($type) {
            'text'     => \Elementor\Controls_Manager::TEXT,
            'textarea' => \Elementor\Controls_Manager::TEXTAREA,
            'number'   => \Elementor\Controls_Manager::NUMBER,
            'select'   => \Elementor\Controls_Manager::SELECT,
            'switch'   => \Elementor\Controls_Manager::SWITCHER,
            default    => \Elementor\Controls_Manager::TEXT,
        };

    }

}