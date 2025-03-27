<?php

namespace Digitalis;

class Attributes implements \ArrayAccess {

    protected $attrs = [];
    protected $quote = "'";

    protected $string = null;

    public function __construct (array $attrs = []) {
    
        $this->attrs = $attrs;
    
    }

    public function __toString () {

        if (is_null($this->string)) {

            $this->string = '';

            foreach ($this->attrs as $name => $value) {
    
                if (is_array($value)) $value = ($name == 'style') ? $this->generate_css($value) : implode(' ',  array_unique($value));

                $this->string .= ($this->string ? ' ' : '') . $name;
                if ($value) $this->string .= "={$this->quote}{$value}{$this->quote}";
    
            }

        }
    
        return $this->string;

    }

    public function set_quote ($quote) {

        $this->string = null;
        $this->quote  = $quote;
    
    }

    //

    public function get_attrs () {

        return $this->attrs;

    }

    public function set_attrs ($attrs) {

        $this->string = null;
        $this->attrs  = $attrs;
        return $this;

    }

    public function get_attr ($attr) {
    
        return $this->attrs[$attr] ?? null;
    
    }

    public function set_attr ($attr, $value = '') {

        $this->string = null;

        if ($attr instanceof self) $attr = $attr->get_attrs();

        if (is_array($attr)) {

            foreach ($attr as $name => $value) $this->set_attr($name, $value);

        } else if ($attr) {

            if (!is_scalar($value)) $value = json_encode($value);
            $this->attrs[$attr] = $value;

        }

        return $this;
    
    }

    public function remove_attr ($attr) {
    
        $this->string = null;
        unset($this->attrs[$attr]);
        return $this;
    
    }

    public function has_attr ($attr) {
    
        return isset($this->attrs[$attr]);
    
    }

    public function get_attributes   () { return call_user_func_array([$this, 'get_attrs'], func_get_args());   }
    public function set_attributes   () { return call_user_func_array([$this, 'set_attrs'], func_get_args());   }
    public function get_attribute    () { return call_user_func_array([$this, 'get_attr'], func_get_args());    }
    public function set_attribute    () { return call_user_func_array([$this, 'set_attr'], func_get_args());    }
    public function remove_attribute () { return call_user_func_array([$this, 'remove_attr'], func_get_args()); }
    public function has_attribute    () { return call_user_func_array([$this, 'has_attr'], func_get_args());    }

    //

    public function get_id () {
    
        return $this->get_attr('id');
    
    }

    public function set_id ($id) {
    
        if ($id) $this->set_attr('id', $id);
        return $this;
    
    }

    public function has_id () {
    
        return $this->has_attr('id');
    
    }

    public function get_class () {

        return $this->get_attr('class');

    }

    public function has_class ($class) {

        if (!isset($this->attrs['class'])) return false;
        return in_array($class, $this->attrs['class']);

    }

    public function add_class (...$classes) {

        foreach ($classes as $class) {

            if (is_array($class)) {

                call_user_func_array([$this, 'add_class'], $class);

            } else {

                if ($class) {

                    $this->string = null;
                    if (!isset($this->attrs['class'])) $this->attrs['class'] = [];
                    $this->attrs['class'][] = $class;

                }

            }

        }

        return $this;

    }

    public function get_style () {
    
        return $this->get_attr('style');
    
    }

    public function add_style ($property, $value = '') {

        if (!$property) return $this;

        $this->string = null;

        if (!isset($this->attrs['style'])) $this->attrs['style'] = [];

        if (is_array($property)) {

            $this->attrs['style'] = array_merge($this->attrs['style'], $property);

        } else {

            $this->attrs['style'][$property] = $value;

        }

        return $this;

    }

    public function add_data ($attr, $value = '') {

        if (is_array($attr)) {

            $attr = array_combine(
                array_map(fn($key) => 'data-' . $key, array_keys($attr)), 
                array_values($attr)
            );

        } else {

            $attr = 'data-' . $attr;

        }
    
        return $this->set_attr($attr, $value);
    
    }

    //

    protected function generate_css ($styles) {

        $css = '';

        foreach ($styles as $property => $value) $css .= "{$property}: {$value};";

        return $css;

    }

    // Property Overloading

    public function __get ($attr) {

        return $this->get_attr($attr);

    }

    public function __set ($attr, $value) {

        if (is_null($attr)) {

            return $this->set_attr($value);

        } else {

            return $this->set_attr($attr, $value);

        }

    }

    public function __unset ($attr) {

        return $this->remove_attr($attr);

    }

    public function __isset ($attr) {

        return $this->has_attr($attr);

    }

    // ArrayAccess

    public function offsetGet ($attr) {

        return $this->__get($attr);

    }

    public function offsetSet ($attr, $value) {

        $this->__set($attr, $value);

    }

    public function offsetUnset ($attr) {

        $this->__unset($attr);

    }

    public function offsetExists ($attr) {

        return $this->__isset($attr);

    }

}