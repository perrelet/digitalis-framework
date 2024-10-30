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

    protected static $merge = ['classes', 'styles', 'attributes'];

    public static function get_classes ($p) {
    
        if (!is_array($p['classes'])) $p['classes'] = [$p['classes']];
        return $p['classes'];
    
    }

    public static function get_styles ($p) {
    
        return $p['styles'];
    
    }

    public static function get_attributes ($p) {
    
        if ($p['id']) $p['attributes']['id'] = $p['id'];

        $p['attributes']['class'] = $p['classes'];
        $p['attributes']['style'] = $p['styles'];

        if ($p['href']) $p['attributes']['href'] = $p['href'];

        return $p;
    
    }

    public static function generate_classes ($p) {

        $p['classes'] = implode(' ', static::get_classes($p));

        return $p;

    }

    public static function generate_styles ($p) {

        $css = '';
        if ($styles = static::get_styles($p)) foreach ($styles as $property => $value) $css .= "{$property}: {$value};";
        $p['styles'] = $css;

        return $p;

    }

    public static function generate_attributes ($p) {

        $atts = '';
        if ($p['attributes']) foreach ($p['attributes'] as $att_name => $att_value) $atts .= " {$att_name}='{$att_value}'";
        $p['attributes'] = $atts;

        return $p;

    }

    public static function get_content ($p) {
    
        return $p['content'];
    
    }

    public static function params ($p) {

        $p = static::generate_classes($p);
        $p = static::generate_styles($p);
        $p = static::get_attributes($p);
        $p = static::generate_attributes($p);
        
        $p['content'] = static::get_content($p);

        return $p;

    }

}