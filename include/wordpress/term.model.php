<?php

namespace Digitalis;

use stdClass;
use WP_Term;
use WP_Term_Query;
use WP_Error;

class Term extends Model {

    protected static $taxonomy = null;

    protected $term_id;
    protected $wp_term;
    protected $children = [];

    public static function process_data (&$data) {

        if (is_array($data)) $data = (object) $data;

    }

    public static function extract_id ($data = null) {

        if (is_null($data))            return ($obj = get_queried_object()) ? ($obj->term_id ?? null ): null;
        if ($data instanceof WP_Term)  return $data->term_id;
        if ($data instanceof stdClass) return 'new';
        if ($data == 'new')            return 'new';

        return (int) parent::extract_id($data);

    }

    public static function extract_uid ($id, $data = null) {

        if ($id == 'new') return random_int(1000000000, PHP_INT_MAX);

        return parent::extract_uid($id, $data);

    }

    public static function validate_id ($id) {

        if ($id == 'new') return true;

        if (!is_int($id) || ($id <= 0))                                   return false;
        if (!$wp_term = get_term($id, static::$taxonomy))                 return false;
        if (static::$taxonomy && $wp_term->taxonomy != static::$taxonomy) return false;

        return true;

    }

    public static function get_specificity () {
    
        return (int) ((bool) static::$taxonomy);
    
    }

    public static function get_by ($field, $value) {

        if (!$wp_term = get_term_by($field, $value, static::$taxonomy)) return;
        if (!$term    = static::get_instance($wp_term->term_id))        return;

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

    public function __construct ($data = null, $uid = null, $id = null) {

        parent::__construct($data, $uid, $id);

        if ($this->id == 'new') {

            if (!is_object($this->data)) $this->data = new stdClass();

            $this->data->term_id  = $this->uid;
            $this->data->taxonomy = static::$taxonomy;

            $this->set_wp_term('new', $this->data);

        } else {

            $this->set_wp_term($this->id);

        }

    }

    public function get_wp_term () {

        return $this->wp_term;

    }

    protected function set_wp_term ($term, $data = null) {

        $term_id = false;

        if (is_int($term))                { $term_id = $term;          }
        elseif (is_string($term))         { $term_id = (int) $term;    }
        elseif ($term instanceof WP_Term) { $term_id = $term->term_id; }

        if ($term_id) {

            $this->term_id = $term_id;
            $this->wp_term = $term instanceof WP_Term ? $term : WP_Term::get_instance($term_id, static::$taxonomy);
            $this->id      = $term_id;

        } elseif ($term == 'new' && ($data instanceof stdClass)) {

            $this->term_id = $term;
            $this->wp_term = new WP_Term($data);

            wp_cache_set($data->term_id, $this->wp_term, 'terms');

            $this->id = $term_id;

        }

    }

    public function get_id() {

        return $this->wp_term->term_id; // Pass the id directly from the wp_term instance to handle new terms. ($this->wp_term->ID = random integer, $this->term_id = 'new')

    }

    public function get_children () {
    
        return $this->children;
    
    }

    //

    public function get_meta ($key, $single = true) {

        return get_term_meta($this->wp_term->term_id, $key, $single);

     }

    public function add_meta ($key, $value, $unique = false) {

        return add_term_meta($this->wp_term->term_id, $key, $value, $unique);

    }

    public function update_meta ($key, $value, $prev_value = '') {

        return update_term_meta($this->wp_term->term_id, $key, $value, $prev_value);

    }

    public function get_field ($selector, $format_value = true) {

        return get_field($selector, "term_{$this->wp_term->term_id}", $format_value);

    }

    public function update_field ($selector, $value) {

        return update_field($selector, $value, "term_{$this->wp_term->term_id}");

    }

    public function update_fields ($data) {
    
        if ($data) foreach ($data as $selector => $value) $this->update_field($selector, $value);
    
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

        return get_term_link($this->wp_term->term_id);

    }

    public function get_feed ($feed = '') {
    
        return get_term_feed_link($this->wp_term->term_id, '', $feed);
    
    }

    // CRUD Methods

    public function reload () {
    
        $this->wp_term = get_term($this->get_id(), static::$taxonomy);
    
    }

    public function save ($term_array = []) {

        $taxonomy = static::$taxonomy ? static::$taxonomy : ($term_array['taxonomy'] ?? false);
        if (!$taxonomy) return;

        $term_array = wp_parse_args($term_array, get_object_vars($this->wp_term));

        if ($this->term_id == 'new') {

            if (!$name = ($term_array['name'] ?? false)) return;
            unset($term_array['name']);
            $result = wp_insert_term($name, $taxonomy, $term_array);

        } else {

            $result = wp_update_term($this->get_id(), $taxonomy, $term_array);

        }

        if (is_wp_error($result)) return $result;

        $term_id = $result['term_id'];

        $this->term_id          = $term_id;
        $this->wp_term->term_id = $term_id;
        $this->id               = $term_id;
        $this->reload();

        if ($term_array['field_input'] ?? false) $this->update_fields($term_array['field_input']);

        return $this;

    }

    public function delete ($args = []) {

        $taxonomy;
        if (!$taxonomy = static::$taxonomy ? static::$taxonomy : ($args['taxonomy'] ?? false)) return;
    
        return wp_delete_term($this->wp_term->term_id, $taxonomy, $args);
    
    }

}