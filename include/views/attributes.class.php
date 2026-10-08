<?php

namespace Lattice;

class Attributes implements \ArrayAccess {

    protected $attrs = [];
    protected $quote = "'";

    protected $string   = null;

    public function __construct (array $attrs = []) {
    
        $this->set_attrs($attrs);
    
    }

    public function __toString () {

        if (!is_null($this->string)) return $this->string;

        $out = [];

        foreach ($this->attrs as $name => $value) {

            $name = strtolower((string) $name);

            // HTML's attribute-name grammar minus `<`: whitespace, quotes, /, = and angle brackets break out of the tag.
            if (!preg_match('/^[^\s"\'<>\/=\x00-\x1F\x7F]+$/', $name)) {

                Strict::fail(static::class, "attribute name '{$name}' cannot be rendered.", 'An attribute name cannot contain whitespace, quotes, /, = or angle brackets.');
                continue;

            }

            $value = $this->normalize_value($name, $value);

            if (is_null($value)) continue;

            $escaped = $this->sanitize_value($name, $value);

            if ($escaped === '' && $value !== '') { // A URL esc_url_raw() rejected (a disallowed scheme): no attribute, not a self-link.

                Strict::fail(static::class, "attribute '{$name}' has a URL esc_url_raw() rejects: " . (strlen($value) > 40 ? substr($value, 0, 37) . '...' : $value), 'Pass an http(s), mailto, tel or other allowed-protocol URL; javascript: and data: are refused.');
                continue;

            }

            $out[] = ($value === '') ? $name : $name . '=' . $this->quote . $this->escape_for_output($escaped) . $this->quote;

        }

        return $this->string = implode(' ', $out);

    }

    // A class list is always a list of tokens: a string (or Stringable) splits on whitespace, a nested list is flattened.
    protected function class_tokens (mixed $value) {

        if (is_array($value)) return array_merge([], ...array_map(fn ($v) => $this->class_tokens($v), array_values($value)));

        return array_values(array_filter(preg_split('/\s+/', trim((string) $value)))); // '' and '0' go, as generate_classes() drops them

    }

    protected function normalize_value (string $name, mixed $value) {

        if ($value === null || $value === false) return null;
        if ($value === true)                     return '';

        if (is_array($value)) {

            $value = match (true) {
                $name === 'class'               => $this->generate_classes($value) ?: null, // no tokens, no attribute
                $name === 'style'               => $this->generate_css($value),
                str_starts_with($name, 'data-') => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                default                         => implode(' ', array_map('strval', $value)),
            };

        } else {

            $value = (string) $value;

        }

        return $value;

    }

    protected function sanitize_value (string $name, mixed $value) {

        return match (true) {
            in_array($name, ['href', 'src', 'action', 'formaction', 'poster'], true) => esc_url_raw($value),
            default => $value,
        };

    }

    protected function escape_for_output (string $value) {

        return esc_attr($value);

    }

    protected function generate_classes (array $classes) {

        return implode(' ', array_unique($classes)); // the list is tokenised on every write path (set_attr, add_class)

    }

    protected function generate_css (array $styles) {

        $css = '';

        foreach ($styles as $property => $value) $css .= "{$property}: {$value};";

        return $css;

    }

    //

    public function set_quote (string $quote) {

        $this->string = null;
        $this->quote  = $quote;
    
    }

    //

    public function get_attrs () {

        return $this->attrs;

    }

    public function set_attrs (mixed $attrs) {

        $this->string = null;
        $this->attrs  = [];

        foreach ((array) $attrs as $name => $value) $this->set_attr($name, $value);

        return $this;

    }

    public function get_attr (int|string $attr) {
    
        return $this->attrs[$attr] ?? null;
    
    }

    public function set_attr (mixed $attr, mixed $value = '') {

        $this->string = null;

        if ($attr instanceof self) $attr = $attr->get_attrs();

        if (is_array($attr)) {

            foreach ($attr as $name => $value) $this->set_attr($name, $value);

        } else {

            if (is_int($attr)) [$attr, $value] = [(string) $value, true]; // A list entry is a boolean attribute: ['required'].
            if ($attr === 'class' && !is_bool($value) && $value !== null) $value = $this->class_tokens($value); // add_class() and has_class() work on a list; null/false still mean "omit", a list entry stays boolean

            if ((string) $attr !== '') $this->attrs[$attr] = $value;

        }

        return $this;
    
    }

    public function add_attrs (mixed $attrs) {
    
        return $this->set_attr($attrs);
    
    }

    public function remove_attr (int|string $attr) {
    
        $this->string = null;
        unset($this->attrs[$attr]);
        return $this;
    
    }

    public function has_attr (int|string $attr) {
    
        return isset($this->attrs[$attr]);
    
    }

    public function get_attributes   (mixed ...$args) { return $this->get_attrs(...$args);   }
    public function set_attributes   (mixed ...$args) { return $this->set_attrs(...$args);   }
    public function add_attributes   (mixed ...$args) { return $this->add_attrs(...$args);   }
    public function get_attribute    (mixed ...$args) { return $this->get_attr(...$args);    }
    public function set_attribute    (mixed ...$args) { return $this->set_attr(...$args);    }
    public function remove_attribute (mixed ...$args) { return $this->remove_attr(...$args); }
    public function has_attribute    (mixed ...$args) { return $this->has_attr(...$args);    }

    //

    public function get_id () {
    
        return $this->get_attr('id');
    
    }

    public function set_id (mixed $id) {
    
        if ($id) $this->set_attr('id', $id);
        return $this;
    
    }

    public function has_id () {
    
        return $this->has_attr('id');
    
    }

    public function get_class () {

        return $this->get_attr('class');

    }

    public function has_class (mixed $class) {

        if (!isset($this->attrs['class']) || !is_array($this->attrs['class'])) return false; // unset, or a boolean `class` entry

        $wanted = $this->class_tokens($class); // 'a b' asks for both tokens

        return $wanted && !array_diff($wanted, $this->attrs['class']);

    }

    public function add_class (mixed ...$classes) {

        $this->string = null;

        if (isset($this->attrs['class']) && !is_array($this->attrs['class'])) $this->attrs['class'] = []; // only a boolean `class` entry can be a non-list here; it has no tokens

        foreach ($this->class_tokens($classes) as $class) $this->attrs['class'][] = $class;

        return $this;

    }

    public function get_style () {
    
        return $this->get_attr('style');
    
    }

    public function add_style (mixed $property, mixed $value = '') {

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

    public function add_data (string|array $attr, mixed $value = '') {

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