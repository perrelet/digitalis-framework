<?php

namespace Digitalis;

abstract class Woocommerce_Theme extends Theme {

    public function __construct() {

        add_filter('woocommerce_locate_template', [$this, 'woocommerce_locate_template'], 1, 3);

        parent::__construct();
        
    }

    public function woocommerce_locate_template ($template, $template_name, $template_path) {

        $template_directory = trailingslashit($this->path) . 'woocommerce/';
        $path = $template_directory . $template_name;
    
        jprint($template_directory);

        return file_exists($path) ? $path : $template;
    
    }

}
