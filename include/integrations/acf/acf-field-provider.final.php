<?php

namespace Lattice;

final class ACF_Field_Provider implements Field_Provider {

    // Registers itself when the sweep walks this file, so core calls no ACF function; Custom_Fields::register() later replaces it.
    public static function static_init () {

        Custom_Fields::register(new static);

    }

    public function available () {

        return function_exists('get_field');

    }

    // ACF's own id scheme: the post id as an int, 'term_N', 'user_N', 'comment_N', 'option'. No meta type (the Has_WP_Meta stub) is no id.
    public function id (Model $model) {

        if ($model->is_new())                           return null;
        if (!method_exists($model, 'get_wp_meta_type')) return null;
        if (!$type = $model->get_wp_meta_type())        return null;

        if ($type === 'option') return 'option';
        if ($type === 'post')   return (int) $model->get_meta_id();

        return $type . '_' . $model->get_meta_id();

    }

    public function get ($id, $selector, $format = true, $escape = false) {

        return get_field($selector, $id, $format, $escape);

    }

    public function get_all ($id, $format = true, $escape = false) {

        return get_fields($id, $format, $escape);

    }

    public function get_object ($id, $selector, $format = true, $load = true, $escape = false) {

        return get_field_object($selector, $id, $format, $load, $escape);

    }

    public function get_objects ($id, $format = true, $load = true, $escape = false) {

        return get_field_objects($id, $format, $load, $escape);

    }

    public function has_rows ($id, $selector) {

        return have_rows($selector, $id);

    }

    public function update ($id, $selector, $value) {

        return update_field($selector, $value, $id);

    }

    public function update_sub ($id, $selector, $value) {

        return update_sub_field($selector, $value, $id);

    }

    public function delete ($id, $selector) {

        return delete_field($selector, $id);

    }

    public function delete_sub ($id, $selector) {

        return delete_sub_field($selector, $id);

    }

    public function add_row ($id, $selector, $row) {

        return add_row($selector, $row, $id);

    }

    public function add_sub_row ($id, $selector, $row) {

        return add_sub_row($selector, $row, $id);

    }

    public function update_row ($id, $selector, $i, $row) {

        return update_row($selector, $i, $row, $id);

    }

    public function update_sub_row ($id, $selector, $i, $row) {

        return update_sub_row($selector, $i, $row, $id);

    }

    public function delete_row ($id, $selector, $i) {

        return delete_row($selector, $i, $id);

    }

    public function delete_sub_row ($id, $selector, $i) {

        return delete_sub_row($selector, $i, $id);

    }

}
