<?php

namespace Digitalis;

use stdClass;
use WP_Term;
use WP_Term_Query;

class Term extends Model {

    protected static $taxonomy = null;

    protected $wp_term;
    protected $children = [];

    public static function extract_id ($data = null) {

        if (is_null($data))           return ($obj = get_queried_object()) ? ($obj->term_id ?? null ): null;
        if ($data instanceof WP_Term) return $data->term_id;

        return (int) parent::extract_id($data);

    }

    public static function validate_id ($id) {

        //if ($id == 'new') return true;

        if (!is_int($id) || ($id <= 0))                                   return false;
        if (!$wp_term = get_term($id, static::$taxonomy))                 return false;
        if (static::$taxonomy && $wp_term->taxonomy != static::$taxonomy) return false;

        return true;

    }

    public static function get_by ($field, $value) {

        if (!$wp_term = get_term_by($field, $value, static::$taxonomy)) return;
        if (!$term    = static::get_instance($wp_term->ID))             return;

        $term->set_wp_term($wp_term);

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
            static::build_hierarchy($instances, $hierarchy);
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

    public function get_wp_term () {

        if (is_null($this->wp_term)) $this->wp_term = get_term($this->id, static::$taxonomy);

        return $this->wp_term;

    }

    public function set_wp_term ($wp_term) {

        if (!($wp_term instanceof WP_Term)) return;

        $this->wp_term = $wp_term;

    }

    public function get_children () {
    
        return $this->children;
    
    }

    //

    public function get_meta ($key, $single = true) {

        return get_term_meta($this->id, $key, $single);

     }

    public function add_meta ($key, $value, $unique = false) {

        return add_term_meta($this->id, $key, $value, $unique);

    }

    public function update_meta ($key, $value, $prev_value = '') {

        return update_term_meta($this->id, $key, $value, $prev_value);

    }

    public function get_field ($selector, $format_value = true) {

        return get_field($selector, "term_{$this->id}", $format_value);

    }

    public function update_field ($selector, $value) {

        return update_field($selector, $value, "term_{$this->id}");

    }

    //

    public function get_slug () {

        return $this->get_wp_term()->slug;

    }

    public function get_name () {

        return $this->get_wp_term()->name;

    }

    public function get_term_group () {

        return $this->get_wp_term()->term_group;

    }

    public function get_term_taxonomy_id () {

        return $this->get_wp_term()->term_taxonomy_id;

    }

    public function get_taxonomy () {

        return $this->get_wp_term()->taxonomy;

    }

    public function get_description () {

        return $this->get_wp_term()->description;

    }

    public function get_parent_id () {

        return $this->get_wp_term()->parent;

    }

    public function get_parent () {

        return static::get_instance($this->get_parent_id());

    }

    public function get_all_parents ($asc = true) {

        $parents = [];
        $parent  = $this;
    
        while ($parent = $parent->get_parent()) $parents[] = $parent;

        if (!$asc) $parents = array_reverse($parents);

        return $parents;
    
    }

    public function get_count () {

        return $this->get_wp_term()->count;

    }

    public function get_url () {

        return get_term_link($this->id);

    }

    public function get_feed ($feed = '') {
    
        return get_term_feed_link($this->id, '', $feed);
    
    }

}