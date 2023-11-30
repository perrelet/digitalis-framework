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
        'content'       => '',
    ];

    protected static $merge = ['classes', 'styles', 'attributes'];

    public static function generate_classes ($p) {

        if (!is_array($p['classes'])) $p['classes'] = [$p['classes']];
        $p['classes'] = implode(' ', $p['classes']);

        return $p;

    }

    public static function generate_styles ($p) {

        $styles = '';
        if ($p['styles']) foreach ($p['styles'] as $property => $value) $styles .= "{$property}: {$value};";
        $p['styles'] = $styles;

        return $p;

    }

    public static function get_attributes ($p) {
    
        if ($p['id']) $p['attributes']['id'] = $p['id'];

        $p['attributes']['class'] = $p['classes'];
        $p['attributes']['style'] = $p['styles'];

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