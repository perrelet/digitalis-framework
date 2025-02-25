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

    public function get_attributes () {

        return $this->attributes;

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

        //

        public function offsetGet ($attribute) {

            return $this->get_attributes()->get_attribute($attribute);
    
        }
    
        public function offsetSet ($attribute, $value) {
    
            $this->get_attributes()->set_attribute($attribute, $value);
    
        }
    
        public function offsetUnset ($attribute) {
    
            $this->get_attributes()->remove_attribute($attribute);
    
        }
    
        public function offsetExists ($attribute) {
    
            return $this->get_attributes()->has_attribute($attribute);
    
        }

}