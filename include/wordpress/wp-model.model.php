<?php

namespace Digitalis;

abstract class WP_Model extends Model {

    use Has_WP_Model;

    public static function validate_id ($id) {

        return is_int($id) && ($id > 0);

    }

    public static function prepare_data (&$data) {

        if (is_array($data)) $data = (object) $data;

    }

    //

    protected $dirty = false;

    protected function build_instance ($data) {

        $this->init_wp_model($data);
        $this->cache_wp_model();

    }

    protected function hydrate_instance () {

        $this->init_wp_model($this->id);

    }

    public function is_dirty () {

        return $this->dirty;

    }

    public function is_saved () {
    
        return !$this->is_unsaved();
    
    }

    public function is_unsaved () {
    
        return $this->is_new() || $this->is_dirty();
    
    }

    //

    public function get_wp_model_prop ($prop) {
    
        return $this->get_wp_model()->$prop ?? null;

    }

    public function set_wp_model_prop ($prop, $value) {

        if (property_exists($this->get_wp_model(), $prop)) {

            $this->get_wp_model()->$prop = $value;
            $this->dirty = true;
            $this->cache_wp_model();

        }

        return $this;
    
    }

    // Caching

    protected function get_cache_key () {

        return $this->id;
    
    }

    public function get_wp_cache_group () {
    
        // ..
    
    }

    public function clear_wp_model_cache () {

        // Generally handled via core, however included for custom save workflows (e.g. raw wpdb->update() or bypassing wp_update_post), batch migrations, CLI scripts, or async workers.
        wp_cache_delete($this->get_wp_model_id(), $this->get_wp_cache_group());
        return $this;
    
    }

    protected function cache_wp_model () {

        if ($this->is_saved()) {

            wp_cache_set($this->get_wp_model_id(), $this->get_wp_model(), $this->get_wp_cache_group());

        }/*  else if ($this->dirty_cache) {

            wp_cache_set($this->get_wp_model_id(), $this->get_wp_model(), $this->get_stashed_cache_group(), $this->dirty_cache_ttl());

        } */

        //if (!$this->is_unsaved()) 

        // Expiration for negative ids set to 1 second (the min value) as we don't need these objects to persist.
        // WP Redis doesn't provide a way to skip our negative keys (only via groups which must = 'posts', etc).
        // A second request within the same second will therfore have access to our unsaved wp_model via wp_cache_get.

        //wp_cache_set($this->get_wp_model_id(), $this->get_wp_model(), $this->get_cache_group(), $this->is_unsaved() ? 1 : 0);

    }

    // CRUD

    public function reload () {
    
        $this->unstash();
        $this->is_new() ? $this->build_instance([]) : $this->hydrate_instance();
        $this->dirty = false;

        return $this;
    
    }

}