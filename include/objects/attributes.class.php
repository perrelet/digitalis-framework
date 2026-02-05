<?php

namespace Digitalis;

class Attributes implements \ArrayAccess {

    protected $attrs    = [];
    protected $quote    = "'";
    protected $encoding = 'UTF-8';

    protected $string   = null;

    public function __construct (array $attrs = []) {
    
        $this->attrs = $attrs;
    
    }

    public function __toString () {

        if (!is_null($this->string)) return $this->string;

        $out = [];

        foreach ($this->attrs as $name => $value) {

            $name = strtolower((string) $name);

            //if (!$this->is_safe_attr_name($name)) continue;

            $value = $this->normalize_value($name, $value);

            if (is_null($value)) continue;

            $out[] = ($value === '') ? $name : $name . '=' . $this->quote . esc_attr($value) . $this->quote;

        }

        return $this->string = implode(' ', $out);

    }

    protected function normalize_value ($name, $value) {

        if ($value === null || $value === false) return null;
        if ($value === true)                     return '';

        if (is_array($value)) {

            $value = match (true) {
                $name === 'class'               => $this->generate_classes($value),
                $name === 'style'               => $this->generate_css($value),
                str_starts_with($name, 'data-') => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                default                         => implode(' ', array_map('strval', $value)),
            };

        } else {

            $value = (string) $value;

        }

        return $this->escape_attr($value);

    }

    protected function generate_classes ($classes) {

        $tokens = array_unique(array_filter(array_map('trim', array_map('strval', $classes))));
        return implode(' ', $tokens);

    }

    protected function generate_css ($styles) {

        $css = '';

        foreach ($styles as $property => $value) $css .= "{$property}: {$value};";

        return $css;

    }

    protected function escape_attr ($value) {

        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, $this->encoding, false);

    }

    //

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

            $this->attrs[$attr] = $value;

        }

        return $this;
    
    }

    public function add_attrs ($attrs) {
    
        return $this->set_attr($attrs);
    
    }

    public function remove_attr ($attr) {
    
        $this->string = null;
        unset($this->attrs[$attr]);
        return $this;
    
    }

    public function has_attr ($attr) {
    
        return isset($this->attrs[$attr]);
    
    }

    public function get_attributes   (...$args) { return $this->get_attrs(...$args);   }
    public function set_attributes   (...$args) { return $this->set_attrs(...$args);   }
    public function add_attributes   (...$args) { return $this->add_attrs(...$args);   }
    public function get_attribute    (...$args) { return $this->get_attr(...$args);    }
    public function set_attribute    (...$args) { return $this->set_attr(...$args);    }
    public function remove_attribute (...$args) { return $this->remove_attr(...$args); }
    public function has_attribute    (...$args) { return $this->has_attr(...$args);    }

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

    public function offsetGet (mixed $attr): mixed {

        return $this->__get($attr);

    }

    public function offsetSet ($attr, mixed $value): void {

        $this->__set($attr, $value);

    }

    public function offsetUnset (mixed $attr): void {

        $this->__unset($attr);

    }

    public function offsetExists (mixed $attr): bool {

        return $this->__isset($attr);

    }

}