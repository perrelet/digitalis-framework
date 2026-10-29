<?php

namespace Lattice;

trait Has_Unsaved_Copies {

    protected ?array  $unsaved_values = null;
    protected ?string $unsaved_key    = null;

    // Raw ACF values by field name.
    public function get_unsaved_copy (array $values) : static {

        $copy = clone $this;
        $copy->unsaved_values = $values + ($this->unsaved_values ?? []);
        $copy->unsaved_key    = 'unsaved_' . bin2hex(random_bytes(8));
        $copy->forget_derived();

        return $copy;

    }

    public function is_unsaved_copy () : bool {

        return !is_null($this->unsaved_values);

    }

    public function forget_derived () : void {}

    public function get_field ($selector, $format_value = true, $escape_html = false) {

        if (is_null($this->unsaved_values) || !array_key_exists($selector, $this->unsaved_values)) return parent::get_field($selector, $format_value, $escape_html);

        $value = $this->unsaved_values[$selector];

        if (!$format_value || !function_exists('acf_maybe_get_field') || !($field = acf_maybe_get_field($selector, $this->get_acf_id(), false))) return $value;

        // ACF caches formatted values by post id, sub-fields included.
        return apply_filters('acf/format_value', $value, $this->unsaved_key, $field, $escape_html);

    }

}
