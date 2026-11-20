<?php

declare(strict_types=1);

namespace Lattice;

trait Has_WP_Meta {

    public function get_wp_meta_type () {

        // ..

    }

    public function get_meta_id () {

        return $this->id;

    }

    public function get_meta (int|string $key = '', bool $single = true) {

        return $this->is_new() ? null : get_metadata($this->get_wp_meta_type(), $this->get_meta_id(), $key, $single);

    }

    public function get_all_meta () {

        return $this->get_meta();

    }

    public function add_meta (int|string $key, mixed $value, bool $unique = false) {

        if ($this->is_new()) return null;

        $this->touch_record();

        return add_metadata($this->get_wp_meta_type(), $this->get_meta_id(), $key, $value, $unique);

    }

    public function add_unique_meta (int|string $key, mixed $value) {

        return $this->add_meta($key, $value, true);

    }

    public function update_meta (int|string $key, mixed $value, mixed $prev_value = '') {

        if ($this->is_new()) return null;

        $this->touch_record();

        return update_metadata($this->get_wp_meta_type(), $this->get_meta_id(), $key, $value, $prev_value);

    }

    public function update_metas (mixed $data) {
    
        if (is_array($data)) foreach ($data as $key => $value) $this->update_meta($key, $value);
    
    }

    public function delete_meta (int|string $key, mixed $value = '') {
    
        if ($this->is_new()) return null;

        $this->touch_record();

        return delete_metadata($this->get_wp_meta_type(), $this->get_meta_id(), $key, $value);
    
    }

}