<?php

namespace Lattice;

// The storage behind the model field accessors (Has_Fields). ACF_Field_Provider ships; Custom_Fields::register() replaces it.
// The id comes first in every method; after it the parameters follow ACF's order and stay untyped, because the accessors
// forward consumer arguments exactly as given.
interface Field_Provider {

    public function available ();

    // The provider's own id for a model, or null when the model has none (unsaved, or no meta type).
    public function id (Model $model);

    public function get ($id, $selector, $format = true, $escape = false);

    public function get_all ($id, $format = true, $escape = false);

    public function get_object ($id, $selector, $format = true, $load = true, $escape = false);

    public function get_objects ($id, $format = true, $load = true, $escape = false);

    public function has_rows ($id, $selector);

    public function update ($id, $selector, $value);

    public function update_sub ($id, $selector, $value);

    public function delete ($id, $selector);

    public function delete_sub ($id, $selector);

    public function add_row ($id, $selector, $row);

    public function add_sub_row ($id, $selector, $row);

    public function update_row ($id, $selector, $i, $row);

    public function update_sub_row ($id, $selector, $i, $row);

    public function delete_row ($id, $selector, $i);

    public function delete_sub_row ($id, $selector, $i);

}
