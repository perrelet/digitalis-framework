<?php

namespace Digitalis;

class ACF_AJAX_Form extends View {

    protected static $defaults = [
        'id'               => 'acf_form',
        'acf_form'         => [],
        'acf_ajax_options' => [],
        'featured_image'   => false,
        'dynamically_load' => true,
    ];

    protected static function condition ($params) {

        return function_exists('acf_form');

    }

    public static function params ($p) {

        $p['acf_form'] = wp_parse_args($p['acf_form'], [
            'id' => $p['id'],
        ]);

        if ($p['featured_image']) {

            if (!isset($p['acf_form']['field_groups'])) $p['acf_form']['field_groups'] = [];
            $p['acf_form']['field_groups'] = array_merge(['post-featured-image'], $p['acf_form']['field_groups']);

        }

        /* $p['acf_form']['fields'] = [
            [
                'label'         => 'Image',
                'type'          => 'image',
                'name'          => '_thumbnail_id',
                'key'           => '_thumbnail_id',
            ]
        ]; */

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

}