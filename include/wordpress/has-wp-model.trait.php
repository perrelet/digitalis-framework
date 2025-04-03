<?php

namespace Digitalis;

trait Has_WP_Model {

    public function get_wp_model () {

        // ..

    }

    public function get_wp_model_id () {

        // ..

    }

    public function get_wp_cache_group () {
    
        // ..
    
    }

    protected function cache_wp_model () {

        // Expiration for negative ids set to 1 second (the min value) as we don't need these objects to persist.
        // WP Redis doesn't provide a way to skip our negative keys (only via groups which must = 'posts', etc).
        // A second request within the same second will therfore have access to our unsaved wp_model via wp_cache_get.

        $expiration = ($this->get_wp_model_id() < 0) ? 1 : 0;

        wp_cache_set($this->get_wp_model_id(), $this->get_wp_model(), $this->get_wp_cache_group(), $expiration);

    }

    protected function generate_uuid ($data) {

        return spl_object_id((object) $data) * -1;

    }

}