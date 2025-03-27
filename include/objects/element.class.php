<?php

namespace Digitalis;

class Element implements \ArrayAccess {

    protected $void_tags = [
        'area',
        'base',
        'br',
        'col',
        'embed',
        'hr',
        'img',
        'input',
        'link',
        'meta',
        'source',
        'track',
        'wbr',
    ];

    protected $tag;
    protected $content;
    protected $attributes;

    public function __construct ($tag = 'div', $attributes = [], $content = '') {

        $this->tag        = $tag;
        $this->content    = $content;
        $this->attributes = ($attributes instanceof Attributes) ? $attributes : new Attributes($attributes);
    
    }

    public function __call ($name, $args) {

        return call_user_func_array([$this->get_attributes(), $name], $args);
    
    }

    public function __toString () {

        if (in_array($this->tag, $this->void_tags)) {

            return $this->open();

        } else {

            return "{$this->open()}{$this->get_content()}{$this->close()}";

        }

    }

    public function open () {
    
        if ($attributes = $this->attributes->__toString()) $attributes = ' '. $attributes;

        return "<{$this->tag}{$attributes}>";
    
    }

    public function close () {

        return in_array($this->tag, $this->void_tags) ? "" : "</{$this->tag}>";

    }

    //

    public function get_attrs () {

        return $this->attributes;

    }

    public function get_attributes () {

        return call_user_func_array([$this, 'get_attrs'], func_get_args());

    }

    public function get_tag () {

        return $this->tag;

    }

    public function set_tag ($tag) {

        $this->tag = $tag;
        return $this;

    }

    public function get_content () {

        return $this->content;

    }

    public function set_content ($content) {

        $this->content = $content;
        return $this;

    }

    // Property Overloading

    public function __get ($attr) {

        return $this->get_attrs()->get_attr($attr);

    }

    public function __set ($attr, $value) {

        if (is_null($attr)) {

            return $this->get_attrs()->set_attr($value);

        } else {

            return $this->get_attrs()->set_attr($attr, $value);

        }

    }

    public function __unset ($attr) {

        return $this->get_attrs()->remove_attr($attr);

    }

    public function __isset ($attr) {

        return $this->get_attrs()->has_attr($attr);

    }

    // ArrayAccess

    public function offsetGet ($attr) {

        return $this->__get($attr);

    }

    public function offsetSet ($attr, $value) {

        return $this->__set($attr, $value);

    }

    public function offsetUnset ($attr) {

        return $this->__unset($attr);

    }

    public function offsetExists ($attr) {

        return $this->__isset($attr);

    }

}