<?php

namespace Digitalis;

class ACF_AJAX_Form extends View {

    protected static $defaults = [
        'id'                  => 'acf_form',
        'acf_form'            => [],
        'acf_ajax_options'    => [],
        'featured_image'      => false,        // Requires \Digitalis\ACF\Featured_Image_Group::load();
        'dynamically_load'    => true,
        'extra_fields_before' => [],
        'extra_fields_after'  => [],
    ];

    protected static function condition ($params) {

        return function_exists('acf_form');

    }

    public static function params ($p) {

        $p['acf_form'] = wp_parse_args($p['acf_form'], [
            'id'                 => $p['id'],
            'html_before_fields' => '',
            'html_after_fields'  => '',
        ]);

        if ($p['featured_image'] && acf_get_field_group('post-featured-image')) {

            if (!isset($p['acf_form']['field_groups'])) $p['acf_form']['field_groups'] = [];
            $p['acf_form']['field_groups'] = array_merge(['post-featured-image'], $p['acf_form']['field_groups']);

        }

        if ($p['extra_fields_before']) $p['acf_form']['html_before_fields'] .= static::get_fields_html($p['extra_fields_before']);
        if ($p['extra_fields_after'])  $p['acf_form']['html_after_fields']  .= static::get_fields_html($p['extra_fields_after']);

        $p['acf_ajax_options'] = wp_parse_args($p['acf_ajax_options'], [
            'form_selector' => '#' . $p['id'],
        ]);

        return $p;
    
    }

    protected static function before_first ($p) {

        echo "<script src='" . DIGITALIS_FRAMEWORK_URI . "assets/js/acf-ajax.class.js?" . DIGITALIS_FRAMEWORK_VERSION . "'></script>";

    }

    public static function view ($p = []) {

        acf_form($p['acf_form']);

        if ($p['dynamically_load']) echo "<script>acf.do_action('append', jQuery('#{$p['id']}'));</script>";

        echo "<script>new ACF_AJAX(" . json_encode($p['acf_ajax_options']) . ");</script>";

    }

    protected static function get_fields_html ($fields) {
    
        ob_start(); 
        acf_render_fields($fields);
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    
    }

}