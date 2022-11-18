<?php

namespace Digitalis;

abstract class Woocommerce_Theme extends Theme {

    public function __construct() {

        add_filter('woocommerce_locate_template', [$this, 'woocommerce_locate_template'], 1, 3);

        if (method_exists($this, 'init')) $this->init();

        parent::__construct();
        
    }

    public function woocommerce_locate_template ($template, $template_name, $template_path) {

        $template_directory = trailingslashit($this->path) . 'woocommerce/';
        $path = $template_directory . $template_name;
    
        jprint($template_directory);

        return file_exists($path) ? $path : $template;
    
    }

    // OPTIONAL FEATURES

    protected function normal_field_descriptions () {

        add_filter('woocommerce_form_field', function ($field, $key, $args, $value) {

            return str_replace('<span class="description"', '<span class="normal-description"', $field);

        }, PHP_INT_MAX, 4);

    }

}
