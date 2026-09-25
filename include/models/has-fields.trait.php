<?php

namespace Lattice;

// Field access through the registered Field_Provider (see Custom_Fields). Without one every accessor returns null (get_field_rows: []).
// Presumes a Model with is_new() and get_wp_meta_type().
trait Has_Fields {

    // Called through $this so a subclass can override the id (Customer returns null for the guest user).
    public function get_field_id () {

        return ($p = Custom_Fields::provider()) ? $p->id($this) : null;

    }

    // Truthy guard on purpose: an id of 0 must never reach a provider (ACF falls back to the queried object for a falsy post id).
    protected function field_call ($method, ...$args) {

        return (($p = Custom_Fields::provider()) && ($id = $this->get_field_id())) ? $p->$method($id, ...$args) : null;

    }

    public function get_fields ($format_value = true, $escape_html = false) {

        return $this->field_call('get_all', $format_value, $escape_html);

    }

    public function get_field_objects ($format_value = true, $load_value = true, $escape_html = false) {

        return $this->field_call('get_objects', $format_value, $load_value, $escape_html);

    }

    public function get_field ($selector, $format_value = true, $escape_html = false) {

        return $this->field_call('get', $selector, $format_value, $escape_html);

    }

    public function esc_field ($selector) {

        return $this->get_field($selector, true, true);

    }

    public function get_field_object ($selector, $format_value = true, $load_value = true, $escape_html = false) {

        return $this->field_call('get_object', $selector, $format_value, $load_value, $escape_html);

    }

    public function field_has_rows ($selector) {

        return $this->field_call('has_rows', $selector);

    }

    public function get_field_rows ($selector, $row_class = ACF_Row::class) {

        $rows = $this->get_field($selector);
        if (!is_array($rows)) return [];

        $items = [];

        foreach (array_values($rows) as $index => $_) {

            $items[] = ['parent' => $this, 'selector' => $selector, 'index' => $index];

        }

        return $row_class::get_instances($items);

    }

    public function update_field ($selector, $value) {

        return $this->field_call('update', $selector, $value);

    }

    public function update_sub_field ($selector, $value) {

        return $this->field_call('update_sub', $selector, $value);

    }

    public function update_fields ($data) {

        if ($data) foreach ($data as $selector => $value) $this->update_field($selector, $value);

    }

    public function field_add_row ($selector, $row = false) {

        return $this->field_call('add_row', $selector, $row);

    }

    public function field_add_sub_row ($selector, $row = false) {

        return $this->field_call('add_sub_row', $selector, $row);

    }

    public function field_update_row ($selector, $i = 1, $row = false) {

        return $this->field_call('update_row', $selector, $i, $row);

    }

    public function field_update_sub_row ($selector, $i = 1, $row = false) {

        return $this->field_call('update_sub_row', $selector, $i, $row);

    }

    public function field_delete_row ($selector, $i = 1) {

        return $this->field_call('delete_row', $selector, $i);

    }

    public function field_delete_sub_row ($selector, $i = 1) {

        return $this->field_call('delete_sub_row', $selector, $i);

    }

    public function delete_field ($selector) {

        return $this->field_call('delete', $selector);

    }

    public function delete_sub_field ($selector) {

        return $this->field_call('delete_sub', $selector);

    }

}
