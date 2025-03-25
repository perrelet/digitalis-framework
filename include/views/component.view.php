<?php

namespace Digitalis;

class Component extends View {

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

    public static function compute_merge_keys () {

        $merge_keys = parent::compute_merge_keys();

        foreach (static::get_elements() as $element) foreach (self::$merge as $key) $merge_keys[] = "{$element}_{$key}";

        return $merge_keys;
    
    }

    //

    public function params (&$p) {

        if ($content = $this->get_content()) $p['content'] = $content;

        $this->create_element();

        foreach (static::get_elements() as $element) $this->create_element($element);

    }

    public function get_content () {}

    public function create_element ($element = null) {

        $key    = $element ? $element : 'element';
        $prefix = $element ? $element . '_' : '';

        if (($this[$key] ?? 0) instanceof Element) return;

        $element = new Element();

        if ($tag        = ($this["{$prefix}tag"]        ?? null)) $element->set_tag($tag);
        if ($content    = ($this["{$prefix}content"]    ?? null)) $element->set_content($content);
        if ($attributes = ($this["{$prefix}attr"]       ?? null)) $element->set_attribute($attributes);
        if ($attributes = ($this["{$prefix}attributes"] ?? null)) $element->set_attribute($attributes);
        if ($id         = ($this["{$prefix}id"]         ?? null)) $element->set_id($id);
        if ($class      = ($this["{$prefix}class"]      ?? null)) $element->add_class($class);
        if ($class      = ($this["{$prefix}classes"]    ?? null)) $element->add_class($class);
        if ($style      = ($this["{$prefix}style"]      ?? null)) $element->add_style($style);
        if ($style      = ($this["{$prefix}styles"]     ?? null)) $element->add_style($style);

        if (($href = ($this["{$prefix}href"] ?? null)) && ($element->get_tag() == 'a')) $element['href'] = $href;

        $this["{$prefix}attributes"] = $element->get_attributes();

        $this[$key] = $element;
    
    }

}