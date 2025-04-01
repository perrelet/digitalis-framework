<?php

namespace Digitalis;

use stdClass;
use WP_Term;
use WP_Term_Query;

class Term extends Model {

    use Has_WP_Term;

    protected static $taxonomy = null;

    public static function prepare_data (&$data) {

        if (is_array($data)) $data = (object) $data;

    }

    public static function get_global_id () {
    
        global $wp_query;

        $wp_term = ($wp_query && ($term = $wp_query->get_queried_object()) instanceof WP_Term) ? $term : null;

        if ($wp_term) return $wp_term->term_id;
    
    }

    public static function extract_id ($data = null) {

        if ($data instanceof WP_Term) return $data->term_id;
        
        return (int) parent::extract_id($data);

    }

    public static function validate_id ($id) {

        if (!$wp_term = get_term($id, static::$taxonomy))                 return false;
        if (static::$taxonomy && $wp_term->taxonomy != static::$taxonomy) return false;

        return (is_int($id) && ($id > 0));

    }

    public static function get_specificity () {
    
        return (int) ((bool) static::$taxonomy);
    
    }

    public static function get_by ($field, $value) {

        if (!$wp_term = get_term_by($field, $value, static::$taxonomy)) return;
        if (!$term    = static::get_instance($wp_term->term_id))        return;

        return $term;

    }

    public static function get_by_slug ($slug) {
    
        return static::get_by('slug', $slug, static::$taxonomy);
    
    }

    public static function get_by_name ($name) {
    
        return static::get_by('name', $slug, static::$taxonomy);
    
    }

    public static function get_by_term_taxonomy_id ($term_taxonomy_id) {
    
        return static::get_by('term_taxonomy_id', $term_taxonomy_id, static::$taxonomy);
    
    }

    //

    public static function query_post ($post) {
    
        if (is_object($post) && method_exists($post, 'get_id')) $post = $post->get_id();

        $wp_terms = get_the_terms($post, static::$taxonomy);

        return static::get_instances($wp_terms);
    
    }

    public static function query ($args = [], &$query = null) {

        $args = wp_parse_args($args, [
            'hierarchy' => false,
        ]);

        $args = (is_admin() && !wp_doing_ajax()) ? static::get_admin_query_vars($args) : static::get_query_vars($args);
    
        if (static::$taxonomy) $args['taxonomy'] = static::$taxonomy;

        $query    = new WP_Term_Query($args);
        $wp_terms = $query->get_terms();

        $instances = static::get_instances($wp_terms);

        if ($args['hierarchy']) {

            $hierarchy = [];
            static::build_hierarchy($instances, $hierarchy, $args['parent'] ?? ($args['child_of'] ?? 0));
            return $hierarchy;

        }

        return $instances;
    
    }

    protected static function build_hierarchy (&$terms, &$hierarchy, $parent_id = 0) {
    
        if ($terms) foreach ($terms as $i => $term) if ($term->get_parent_id() == $parent_id) {

            $hierarchy[] = $term;
            unset($terms[$i]);

        }

        if ($hierarchy) foreach ($hierarchy as $term) static::build_hierarchy($terms, $term->children, $term->get_id());
    
    }

    public static function get_query_vars ($args = []) {

        return $args;

    }

    public static function get_admin_query_vars ($args = []) {

        return $args;

    }

    //

    protected $children = [];

    protected function generate_uuid ($data) {

        return spl_object_id((object) $data) * -1;

    }

    protected function build_instance ($data) {

        $wp_term          = new WP_Term((object) $data);
        $wp_term->term_id = $this->id;

        if (static::$taxonomy) $wp_term->taxonomy = static::$taxonomy;

        $this->init_wp_model($wp_term);
        $this->cache_wp_model();

    }

    protected function hydrate_instance () {

        $this->init_wp_model($this->id);

    }

    // CRUD Methods

    public function reload () {
    
        if ($this->id) $this->init_wp_model($this->id);
    
    }

    public function save ($term_array = []) {

        $taxonomy = static::$taxonomy ? static::$taxonomy : ($term_array['taxonomy'] ?? false);
        if (!$taxonomy) return; // TODO: HANDLE ERROR

        $term_array = wp_parse_args($term_array, get_object_vars($this->wp_term));

        if ($this->is_new()) {

            if (!$name = ($term_array['name'] ?? false)) return;
            unset($term_array['name']);
            $result = wp_insert_term($name, $taxonomy, $term_array);

        } else {

            $result = wp_update_term($this->get_id(), $taxonomy, $term_array);

        }

        if (is_wp_error($result)) return $result;

        $term_id = $result['term_id'];

        $this->is_new           = false;
        $this->wp_term->term_id = $term_id;
        $this->id               = $term_id;
        $this->reload();

        if ($term_array['field_input'] ?? false) $this->update_fields($term_array['field_input']);

        return $this;

    }

    public function delete ($args = []) {

        $taxonomy;
        if (!$taxonomy = static::$taxonomy ? static::$taxonomy : ($args['taxonomy'] ?? false)) return; // TODO: HANDLE ERROR
    
        return wp_delete_term($this->wp_term->term_id, $taxonomy, $args);
    
    }

}