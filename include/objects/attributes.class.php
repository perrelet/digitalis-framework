<?php

namespace Digitalis;

class Attributes implements \ArrayAccess {

    protected $attributes = [];
    protected $quote      = "'";

    protected $string     = null;

    public function __construct (array $attributes = []) {
    
        $this->attributes = $attributes;
    
    }

    public function __toString() {

        if (is_null($this->string)) {

            $this->string = '';

            foreach ($this->attributes as $name => $value) {
    
                if (is_array($value)) $value = ($name == 'style') ? $this->generate_css($value) : implode(' ',  array_unique($value));
    
                $this->string .= ($this->string ? ' ' : '') . "{$name}={$this->quote}{$value}{$this->quote}";
    
            }

        }
    
        return $this->string;

    }

    public function set_quote ($quote) {

        $this->string = null;
        $this->quote  = $quote;
    
    }

    //

    public function get_attributes () {

        return $this->attributes;

    }

    public function set_attributes ($attributes) {

        $this->string     = null;
        $this->attributes = $attributes;
        return $this;

    }

    public function get_attribute ($attribute) {
    
        return $this->attributes[$attribute] ?? null;
    
    }

    public function set_attribute ($attribute, $value = '') {

        $this->string = null;

        if ($attribute instanceof self) $attribute = $attribute->get_attributes();

        if (is_array($attribute)) {

            foreach ($attribute as $attr => $value) $this->set_attribute($attr, $value);

        } else {

            if ($attribute) $this->attributes[$attribute] = $value;

        }

        return $this;
    
    }

    public function remove_attribute ($attribute) {
    
        $this->string = null;
        unset($this->attributes[$attribute]);
        return $this;
    
    }

    public function has_attribute ($attribute) {
    
        return isset($this->attributes[$attribute]);
    
    }

    //

    public function get_id () {
    
        return $this->get_attribute('id');
    
    }

    public function set_id ($id) {
    
        if ($id) $this->set_attribute('id', $id);
        return $this;
    
    }

    public function get_class () {

        return $this->get_attribute('class');

    }

    public function has_class ($class) {

        if (!isset($this->attributes['class'])) return false;
        return in_array($class, $this->attributes['class']);

    }

    public function add_class (...$classes) {

        foreach ($classes as $class) {

            if (is_array($class)) {

                call_user_func_array([$this, 'add_class'], $class);

            } else {

                if ($class) {

                    $this->string = null;
                    if (!isset($this->attributes['class'])) $this->attributes['class'] = [];
                    $this->attributes['class'][] = $class;

                }

            }

        }

        return $this;

    }

    public function get_style () {
    
        return $this->get_attribute('style');
    
    }

    public function add_style ($property, $value = '') {

        if (!$property) return $this;

        $this->string = null;

        if (!isset($this->attributes['style'])) $this->attributes['style'] = [];

        if (is_array($property)) {

            $this->attributes['style'] = array_merge($this->attributes['style'], $property);

        } else {

            $this->attributes['style'][$property] = $value;

        }

        return $this;

    }

    public function set_data ($attribute, $value = '') {
    
        return $this->set_attribute('data-' . $attribute, $value);
    
    }

    //

    protected function generate_css ($styles) {

        $css = '';

        foreach ($styles as $property => $value) $css .= "{$property}: {$value};";

        return $css;

    }

    //

    public function offsetGet ($attribute) {

        return $this->get_attribute($attribute);

    }

    public function offsetSet ($attribute, $value) {

        $this->set_attribute($attribute, $value);

    }

    public function offsetUnset ($attribute) {

        $this->remove_attribute($attribute);

    }

    public function offsetExists ($attribute) {

        return $this->has_attribute($attribute);

    }

}