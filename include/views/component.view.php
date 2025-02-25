<?php

namespace Digitalis;

class Component extends View {

    protected static $template = 'component';

    protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "templates/digitalis/components/";

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

    public static function get_elements () {
    
        return static::$elements;
    
    }

    public static function prepare_element ($p, $element = null) {

        $key    = $element ? $element : 'element';
        $prefix = $element ? $element . '_' : '';

        $element = new Element();

        if ($p["{$prefix}tag"] ?? null) $element->set_tag($p["{$prefix}tag"]);

        $element->set_content($p["{$prefix}content"] ?? '');
        $element->set_id($p["{$prefix}id"] ?? null);
        $element->add_class($p["{$prefix}classes"] ?? null);
        $element->add_style($p["{$prefix}styles"] ?? null);
        $element->set_attribute($p["{$prefix}attributes"] ?? null);

        if (($href = ($p["{$prefix}href"] ?? null)) && ($element->get_tag() == 'a')) $element['href'] = $href;

        $p["{$prefix}attributes"] = $element->get_attributes();

        $p[$key] = $element;

        return $p;
    
    }

    public static function get_content ($p) {
    
        return $p['content'];
    
    }

    public static function get_merge_keys () {

        $merge_keys = parent::get_merge_keys();

        foreach (static::get_elements() as $element) {

            $merge_keys[] = "{$element}_classes";
            $merge_keys[] = "{$element}_styles";
            $merge_keys[] = "{$element}_attributes";

        }

        return $merge_keys;
    
    }

    public static function get_defaults () {
    
        $defaults = parent::get_defaults();

        foreach (static::get_elements() as $element) {

            if (!isset($defaults["{$element}_classes"]))    $defaults["{$element}_classes"]    = [];
            if (!isset($defaults["{$element}_styles"]))     $defaults["{$element}_styles"]     = [];
            if (!isset($defaults["{$element}_attributes"])) $defaults["{$element}_attributes"] = [];
            if (!isset($defaults["{$element}_content"]))    $defaults["{$element}_content"]    = '';

        }

        return $defaults;
    
    }

    public static function params ($p) {

        $p = static::prepare_element($p);

        foreach (static::get_elements() as $element) $p = static::prepare_element($p, $element);
        
        $p['content'] = static::get_content($p);

        return $p;

    }

}