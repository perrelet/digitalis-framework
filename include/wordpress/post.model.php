<?php

namespace Digitalis;

use stdClass;
use WP_Post;
use WP_Query;

class Post extends Model {

    use Has_WP_Post;

    protected static $post_type       = false;       // string            - Validate by post_type. Leave false to allow any generic post type.
    protected static $post_status     = false;       // string|bool|array - Validate by post_status. Leave false to allow any status.
    protected static $term            = false;       // string|bool|array - Validate by taxonomy term. Leave false to allow any term.
    protected static $taxonomy        = 'category';  // string            - Taxonomy to validate term against.

    protected static $post_type_class = false;       // (deprecated) Used when querying the model to get retrieve query vars.

    public static function prepare_data (&$data) {

        if (is_array($data)) $data = (object) $data;

    }

    public static function get_global_id () {
    
        global $post;

        if ($post instanceof WP_Post) return $post->ID;
    
    }

    public static function extract_id ($data = null) {

        if (is_object($data) && property_exists($data, 'ID'))   return $data->ID;
        if (is_object($data) && method_exists($data, 'get_id')) return $data->get_id();

        return (int) parent::extract_id($data);

    }

    public static function validate_id ($id) {

        if (static::$post_type && (get_post_type($id) != static::$post_type))    return false;
        if (static::$term && (!has_term(static::$term, static::$taxonomy, $id))) return false;

        if (static::$post_status) {

            if (!is_array(static::$post_status)) static::$post_status = [static::$post_status];
            if (!in_array(get_post_status($id), static::$post_status)) return false;

        }

        return (is_int($id) && ($id > 0));

    }

    public static function get_specificity () {
    
        return (int) (((bool) static::$post_type) + ((bool) static::$post_status) * 10 + ((bool) static::$term) * 100);
    
    }

    //

    public static function get_query_var ($key = 'posts_per_page', $default = '', $args = [], $query_key = null, $query = null) {

        if (isset($args[$key])) return $args[$key];

        if (!($query instanceof WP_Query)) {

            global $wp_query;
            $query = $wp_query;

        }

        return $query->get($query_key ? $query_key : $key, $default);

    }

    public static function get_query_vars ($args = []) {

        $call = static::$post_type_class . "::get_query_vars";
        return is_callable($call) ? call_user_func($call) : [];

    }

    public static function get_admin_query_vars ($args = []) {

        $call = static::$post_type_class . "::get_admin_query_vars";
        return is_callable($call) ? call_user_func($call) : [];

    }

    public static function get_by_slug ($slug) {
    
        $post_type = static::$post_type ? static::$post_type : get_post_types();

        if (!$wp_post = get_page_by_path($slug, 'OBJECT', $post_type)) return;
        if (!$post = static::get_instance($wp_post->ID))               return;

        return $post;
    
    }

    public static function query ($args = [], &$query = null, $skip_main = false) {

        global $wp_query;

        $instances = [];
        $posts     = [];

        if (!$skip_main && !wp_doing_ajax() && $wp_query && $wp_query->is_main_query() && static::query_is_post_type($wp_query)) {

            // Use the existing global wp_query.

            $query = $wp_query;
            $posts = $wp_query->posts;

        } else {

            // Build a fresh wp_query.

            $query = new Digitalis_Query();

            if (!$skip_main && $wp_query && $wp_query->is_main_query() && Digitalis_Query::is_multiple($query)) $query->merge($wp_query->query_vars);

            $query->set_var('post_type', static::$post_type ? static::$post_type : 'any');
            if (static::$post_status) $query->set_var('post_status', static::$post_status);
            if (static::$term)        $query->add_tax_query([
                'taxonomy' => static::$taxonomy,
                'field'    => is_int(static::$term) ? 'term_id' : 'slug',
                'terms'    => static::$term,
            ]);
            $query->merge((is_admin() && !wp_doing_ajax()) ? static::get_admin_query_vars($args) : static::get_query_vars($args), true);
            $query->merge($args, true);

            $posts = $query->query();

        }

        return static::get_instances($posts);

    }

    protected static function query_is_post_type ($wp_query) {

        return Digitalis_Query::compare_post_type($wp_query, static::$post_type);

    }

    //

    protected function build_instance ($data) {

        $wp_post     = new WP_Post((object) $data);
        $wp_post->ID = $this->id;

        if (static::$post_type) $wp_post->post_type = static::$post_type;

        $this->init_wp_model($wp_post);
        $this->cache_wp_model();

    }

    protected function hydrate_instance () {

        $this->init_wp_model($this->id);

    }

    public function get_global_var () {

        return static::$post_type ? "_" . static::$post_type : false;

    }

    // Data Access

    public function reload () {
    
        $this->init_wp_model($this->id);
        $this->content_cache = [];
    
    }

    public function save ($post_array = [], $fire_after_hooks = true) {

        $post_array = wp_parse_args($post_array, get_object_vars($this->wp_post));

        if ($this->is_new() && isset($post_array['ID'])) unset($post_array['ID']);

        $tax_input = $post_array['tax_input'] ?? []; // Process the 'tax_input' manually as wp_insert_post check's if there user is allowed to add the tax, which fails for cron. (https://core.trac.wordpress.org/ticket/19373)
        if (isset($post_array['tax_input'])) unset($post_array['tax_input']);

        if ($this->is_new()) {

            $post_id = wp_insert_post($post_array, true, $fire_after_hooks);

        } else {

            $post_array['ID'] = $this->get_id();
            $post_id = wp_update_post($post_array, true, $fire_after_hooks);

        }

        if (is_wp_error($post_id)) return $post_id;

        $this->is_new      = false;
        $this->wp_post->ID = $post_id;
        $this->id          = $post_id;
        $this->reload();

        if ($tax_input) foreach ($tax_input as $taxonomy => $terms) {

            wp_set_post_terms($post_id, $terms, $taxonomy, $post_array['append_terms'] ?? false);

        }

        if ($post_array['field_input'] ?? false) $this->update_fields($post_array['field_input']);

        // TODO: Add to cache

        return $this;

    }

    public function delete ($force_delete = false) {

        // TODO: delete this object? null the cache?
    
        return wp_delete_post($this->wp_post->ID, $force_delete);
    
    }

}