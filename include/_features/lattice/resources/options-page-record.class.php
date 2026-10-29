<?php

declare(strict_types=1);

namespace Lattice;

class Options_Page_Record extends Options_Page {

    use Has_Version, Has_Unsaved_Copies;

    // Where Has_Version keeps its count

    protected function get_option_name (string $key) : string {

        return "{$key}_{$this->get_id()}";

    }

    public function get_meta ($key) {

        return get_option($this->get_option_name($key));

    }

    public function add_meta ($key, $value, $unique = false) {

        return add_option($this->get_option_name($key), $value, '', false);

    }

    // update_option has no compare-and-swap.
    public function update_meta ($key, $value, $prev = null) {

        global $wpdb;

        $name = $this->get_option_name($key);

        if (is_null($prev)) return update_option($name, $value, false);

        $updated = $wpdb->update($wpdb->options, ['option_value' => maybe_serialize($value)], ['option_name' => $name, 'option_value' => maybe_serialize($prev)]);

        wp_cache_delete($name, 'options');

        return (bool) $updated;

    }

}
