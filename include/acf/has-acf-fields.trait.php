<?php

namespace Digitalis;

trait Has_ACF_Fields {

    public function get_acf_id () {

        // ..

    }

    public function get_fields ($format_value = true, $escape_html = false) {

        return ($id = $this->get_acf_id()) ? get_fields($id, $format_value, $escape_html) : null;

    }

    public function get_field_objects ($format_value = true, $load_value = true, $escape_html = false) {

        return ($id = $this->get_acf_id()) ? get_field_objects($id, $format_value, $load_value, $escape_html) : null;

    }

    public function get_field ($selector, $format_value = true, $escape_html = false) {

        return ($id = $this->get_acf_id()) ? get_field($selector, $id, $format_value, $escape_html) : null;

    }

    public function esc_field ($selector) {

        return $this->get_field($selector, true, true);

    }

    public function get_field_object ($selector, $format_value = true, $load_value = true, $escape_html = false) {

        return ($id = $this->get_acf_id()) ? get_field_object($selector, $id, $format_value, $load_value, $escape_html) : null;

    }

    public function field_has_rows ($selector) {

        return ($id = $this->get_acf_id()) ? have_rows($selector, $id) : null;

    }

    public function update_field ($selector, $value) {

        return ($id = $this->get_acf_id()) ? update_field($selector, $value, $id) : null;

    }

    public function update_sub_field ($selector, $value) {

        return ($id = $this->get_acf_id()) ? update_sub_field($selector, $value, $id) : null;

    }

    public function update_fields ($data) {

        if ($data) foreach ($data as $selector => $value) $this->update_field($selector, $value);

    }

    public function field_add_row ($selector, $row = false) {

        return ($id = $this->get_acf_id()) ? add_row($selector, $row, $id) : null;

    }

    public function field_add_sub_row ($selector, $row = false) {

        return ($id = $this->get_acf_id()) ? add_sub_row($selector, $row, $id) : null;

    }

    public function field_update_row ($selector, $i = 1, $row = false) {

        return ($id = $this->get_acf_id()) ? update_row($selector, $i, $row, $id) : null;

    }

    public function field_update_sub_row ($selector, $i = 1, $row = false) {

        return ($id = $this->get_acf_id()) ? update_sub_row($selector, $i, $row, $id) : null;

    }

    public function field_delete_row ($selector, $i = 1) {

        return ($id = $this->get_acf_id()) ? delete_row($selector, $i, $id) : null;

    }

    public function field_delete_sub_row ($selector, $i = 1) {

        return ($id = $this->get_acf_id()) ? delete_sub_row($selector, $i, $id) : null;

    }

    public function delete_field ($selector, $value) {

        return ($id = $this->get_acf_id()) ? delete_field($selector, $value, $id) : null;

    }

    public function delete_sub_field ($selector, $value) {

        return ($id = $this->get_acf_id()) ? delete_sub_field($selector, $value, $id) : null;

    }

}