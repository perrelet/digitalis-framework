<?php

namespace Digitalis;

abstract class Woocommerce_Theme extends Theme {

    public function __construct() {

        add_filter('woocommerce_locate_template', [$this, 'woocommerce_locate_template'], 1, 3);
        add_filter('woocommerce_account_menu_item_classes', [$this, 'account_menu_item_classes'], 10, 2);

        parent::__construct();
        
    }

    public function woocommerce_locate_template ($template, $template_name, $template_path) {

        if (!$this->path) return $template;

        $template_directory = trailingslashit($this->path) . 'woocommerce/';
        $path = $template_directory . $template_name;

        return file_exists($path) ? $path : $template;
    
    }

    public function account_menu_item_classes ($classes, $endpoint) {

        //jprint($endpoint);

        if ($page = Woo_Account_Page::get_page($endpoint)) {

            if ($parent_slug = $page->get_parent()) {

                $classes[] = 'has-parent';

            }

        }

        return $classes;

    }

    // OPTIONAL FEATURES

    protected function normal_field_descriptions () {

        add_filter('woocommerce_form_field', function ($field, $key, $args, $value) {

            return str_replace('<span class="description"', '<span class="normal-description"', $field);

        }, PHP_INT_MAX, 4);

    }

}
