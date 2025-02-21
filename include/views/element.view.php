<?php

namespace Digitalis;

class Element extends View {

    protected static $template = 'element';

    protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "templates/digitalis/elements/";

    protected static $defaults = [
        'id'            => null,
        'tag'           => 'div',
        'classes'       => [],
        'styles'        => [],
        'attributes'    => [],
        'href'          => null,
        'content'       => '',
    ];

    protected static $elements = [];

    protected static $merge = ['classes', 'styles', 'attributes'];

    public static function get_classes ($p, $prefix = '') {
    
        return (array) ($p["{$prefix}classes"] ?? []);
    
    }

    public static function get_styles ($p, $prefix = '') {
    
        return (array) ($p["{$prefix}styles"] ?? []);
    
    }

    public static function get_attributes ($p, $prefix = '') {
    
        if ($p["{$prefix}id"]      ?? '') $p["{$prefix}attributes"]['id']    = $p["{$prefix}id"];
        if ($p["{$prefix}classes"] ?? '') $p["{$prefix}attributes"]['class'] = $p["{$prefix}classes"];
        if ($p["{$prefix}styles"]  ?? '') $p["{$prefix}attributes"]['style'] = $p["{$prefix}styles"];

        if (($href = ($p["{$prefix}href"] ?? '')) && (($p["{$prefix}tag"] ?? '') == 'a')) $p["{$prefix}attributes"]['href'] = $href;

        return $p;
    
    }

    public static function generate_classes ($p, $prefix = '') {

        $p["{$prefix}classes"] = implode(' ', static::get_classes($p, $prefix));

        return $p;

    }

    public static function generate_styles ($p, $prefix = '') {

        $css = '';
        foreach (static::get_styles($p, $prefix) as $property => $value) $css .= "{$property}: {$value};";
        $p["{$prefix}styles"] = $css;

        return $p;

    }

    public static function generate_attributes ($p, $prefix = '') {

        $atts = '';
        foreach ($p["{$prefix}attributes"] ?? [] as $att_name => $att_value) $atts .= " {$att_name}='{$att_value}'";
        $p["{$prefix}attributes"] = $atts;

        return $p;

    }

    public static function prepare_element ($p, $prefix = '') {
    
        $p = static::generate_classes($p, $prefix);
        $p = static::generate_styles($p, $prefix);
        $p = static::get_attributes($p, $prefix);
        $p = static::generate_attributes($p, $prefix);

        return $p;
    
    }

    public static function get_content ($p) {
    
        return $p['content'];
    
    }

    public static function get_merge_keys () {

        $merge_keys = parent::get_merge_keys();

        foreach (static::$elements as $element) {

            $merge_keys[] = "{$element}_classes";
            $merge_keys[] = "{$element}_styles";
            $merge_keys[] = "{$element}_attributes";

        }

        return $merge_keys;
    
    }

    public static function get_defaults () {
    
        $defaults = parent::get_defaults();

        foreach (static::$elements as $element) {

            if (!isset($defaults["{$element}_classes"]))    $defaults["{$element}_classes"]    = [];
            if (!isset($defaults["{$element}_styles"]))     $defaults["{$element}_styles"]     = [];
            if (!isset($defaults["{$element}_attributes"])) $defaults["{$element}_attributes"] = [];
            if (!isset($defaults["{$element}_content"]))    $defaults["{$element}_content"]    = '';

        }

        return $defaults;
    
    }

    public static function params ($p) {

        $p = static::prepare_element($p);

        foreach (static::$elements as $element) $p = static::prepare_element($p, $element . '_');
        
        $p['content'] = static::get_content($p);

        return $p;

    }

}