<?php

namespace Digitalis;

class Deprecated_Component extends Deprecated_View {

    protected static $template = 'component';

    protected static $template_path = DIGITALIS_FRAMEWORK_PATH . "templates/digitalis/components/";

    protected static $defaults = [
        'tag'     => 'div',
        'id'      => null,
        'class'   => [],
        'style'   => [],
        'attr'    => [],
        'href'    => null,
        'content' => '',
    ];

    protected static $elements = [];

    protected static $merge = ['attr', 'attributes', 'class', 'classes', 'style', 'styles', 'data'];

    public static function get_elements () {
    
        return static::$elements;
    
    }

    public static function create_element (&$p, $element = null) {

        $key    = $element ? $element : 'element';
        $prefix = $element ? $element . '_' : '';

        if (($p[$key] ?? 0) instanceof Element) return;

        $element = new Element();

        if ($tag        = ($p["{$prefix}tag"]        ?? null)) $element->set_tag($tag);
        if ($content    = ($p["{$prefix}content"]    ?? null)) $element->set_content($content);
        if ($attributes = ($p["{$prefix}attr"]       ?? null)) $element->set_attribute($attributes);
        if ($attributes = ($p["{$prefix}attributes"] ?? null)) $element->set_attribute($attributes);
        if ($id         = ($p["{$prefix}id"]         ?? null)) $element->set_id($id);
        if ($class      = ($p["{$prefix}class"]      ?? null)) $element->add_class($class);
        if ($class      = ($p["{$prefix}classes"]    ?? null)) $element->add_class($class);
        if ($style      = ($p["{$prefix}style"]      ?? null)) $element->add_style($style);
        if ($style      = ($p["{$prefix}styles"]     ?? null)) $element->add_style($style);

        if (($href = ($p["{$prefix}href"] ?? null)) && ($element->get_tag() == 'a')) $element['href'] = $href;

        $p["{$prefix}attributes"] = $element->get_attributes();

        $p[$key] = $element;
    
    }

    public static function get_content ($p) {
    
        return $p['content'];
    
    }

    public static function compute_merge_keys () {

        $merge_keys = parent::compute_merge_keys();

        foreach (static::get_elements() as $element) foreach (self::$merge as $key) $merge_keys[] = "{$element}_{$key}";

        return $merge_keys;
    
    }

    public static function params ($p) {

        $p['content'] = static::get_content($p);

        static::create_element($p);

        foreach (static::get_elements() as $element) static::create_element($p, $element);

        return $p;

    }

}