<?php

namespace Lattice;

// An ACF options page; its slug is its id.
class Options_Page extends Model {

    use Has_Fields;

    public static function validate_id ($id) {

        return is_string($id) && function_exists('acf_get_options_page') && acf_get_options_page($id);

    }

    public function get_page () : array {

        return (array) acf_get_options_page($this->get_id());

    }

    public function get_title () : string {

        return (string) ($this->get_page()['page_title'] ?? $this->get_id());

    }

    public function get_capability () : string {

        return (string) ($this->get_page()['capability'] ?? 'manage_options');

    }

    public function get_field_id () {

        return $this->get_page()['post_id'] ?? 'options';

    }

    public function get_edit_url () : string {

        return admin_url('admin.php?page=' . $this->get_id());

    }

    // ACF writes each field as it is updated.
    public function save () {

        return $this;

    }

}
