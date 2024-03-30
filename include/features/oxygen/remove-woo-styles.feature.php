<?php

namespace Digitalis\Oxygen;

use Digitalis\Feature;

class Remove_Woo_Styles extends Feature {

    public function run () {

        // https://github.com/soflyy/oxygen-bugs-and-features/issues/3221#issuecomment-2024110807

        add_action('plugins_loaded', [$this, 'remove_woo_styles'], 20);
        add_filter('oxy_elements_api_page_css_output', [$this, 'OxyWooCommerce__filter_global_settings']);

    }

    public function remove_woo_styles () {
            
        global $wp_filter;

        $actions = $wp_filter['oxygen_default_classes_output'][10] ?? null;

        if ($actions) foreach ($actions as $action) {

            if (!$function = $action['function'] ?? null) continue;
            if (!is_array($function))                     continue;
            if (!$object = $function[0] ?? null)          continue;
            if (!($object instanceof \OxyWooCommerce))    continue;

            remove_action('oxygen_default_classes_output', $function, 10);

        }

    }

    public function OxyWooCommerce__filter_global_settings( $css ) {

        // remove variables definitions
        $css = preg_replace('%\/\*STRIP START\*\/(.*?)\/\*STRIP END\*\/%s', '', $css);

        $global_settings = ct_get_global_settings();

        if (isset($global_settings['woo'])){

            // units
            foreach ($global_settings['woo'] as $key => $value) {
                if (isset($global_settings['woo'][$key."-unit"])) {
                    $global_settings['woo'][$key] = $value.$global_settings['woo'][$key."-unit"];
                }
            }
        
            $options = array_keys  ($global_settings['woo']);
            $values  = array_values($global_settings['woo']);

            $options = array_map(function($value){
                return "var($value)";
            }, $options);

            // global colors
            $values = array_map(function($value){
                return oxygen_vsb_get_global_color_value($value);
            }, $values);
            
            $css = str_replace($options, $values, $css);
        }
      
        return $css;

    }

}