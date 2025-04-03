<?php

namespace Digitalis;

trait Has_WP_Meta {

    public function get_wp_meta_type () {

        // ..

    }

    public function get_meta_id () {

        return $this->id;

    }

    public function get_meta ($key = '', $single = true) {

        return $this->is_new() ? null : get_metadata($this->get_wp_meta_type(), $this->get_meta_id(), $key, $single);

    }

    public function get_all_meta () {

        return $this->get_meta();

    }

    public function add_meta ($key, $value, $unique = false) {

        return $this->is_new() ? null : add_metadata($this->get_wp_meta_type(), $this->get_meta_id(), $key, $value, $unique);

    }

    public function add_unique_meta ($key, $value) {

        return $this->add_meta($key, $value, true);

    }

    public function update_meta ($key, $value, $prev_value = '') {

        return $this->is_new() ? null : update_metadata($this->get_wp_meta_type(), $this->get_meta_id(), $key, $value, $prev_value);

    }

    public function update_metas ($data) {
    
        if (is_array($data)) foreach ($data as $key => $value) $this->update_meta($key, $value);
    
    }

    public function delete_meta ($key, $value = '') {
    
        return $this->is_new() ? null : delete_metadata($this->get_wp_meta_type(), $this->get_meta_id(), $key, $value);
    
    }

}