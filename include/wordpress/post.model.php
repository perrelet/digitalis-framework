<?php

namespace Digitalis;

use stdClass;
use WP_Post;
use WP_Query;

class Post extends WP_Model {

    use Has_WP_Post;

    protected static $post_type      = false;       // string            - Validate by post_type. Leave false to allow any generic post type.
    protected static $post_status    = false;       // string|bool|array - Validate by post_status. Leave false to allow any status.
    protected static $term           = false;       // string|bool|array - Validate by taxonomy term. Leave false to allow any term.
    protected static $term_deep      = false;       // bool              - When true, $term also matches any descendant term (mirrors WP_Query tax_query include_children).
    protected static $taxonomy       = 'category';  // string            - Taxonomy to validate term against.
    protected static $post_slug      = false;       // string            - Validate by post_name (URL slug).
    protected static $post_slug_deep = false;       // bool              - When true, $post_slug also matches any descendant post (mirrors $term / $term_deep).
    protected static $post_context   = false;       // string            - One of $context_options keys (e.g. 'front_page', 'posts', 'privacy').

    protected static $context_options = [
        'front_page' => 'page_on_front',
        'posts'      => 'page_for_posts',
        'privacy'    => 'wp_page_for_privacy_policy',
    ];

    protected static $post_type_class = false;       // (deprecated) Used when querying the model to get retrieve query vars.

    public static function get_global_id () {

        if (static::$post_context) {

            $key = static::$context_options[static::$post_context] ?? null;
            if ($key) return ((int) get_option($key)) ?: null;

        }

        global $post;

        if ($post instanceof WP_Post) return $post->ID;

    }

    public static function extract_id ($data = null) {

        if (is_object($data) && property_exists($data, 'ID'))   return $data->ID;
        if (is_object($data) && method_exists($data, 'get_id')) return $data->get_id();

        return (int) parent::extract_id($data);

    }

    public static function validate_id ($id) {

        if (static::$post_type && (get_post_type($id) != static::$post_type)) return false;

        if (static::$term) {

            $terms = static::$term_deep
                ? static::resolve_term_set(static::$term, static::$taxonomy)
                : static::$term;

            if (!has_term($terms, static::$taxonomy, $id)) return false;

        }

        if (static::$post_status) {

            if (!is_array(static::$post_status)) static::$post_status = [static::$post_status];
            if (!in_array(get_post_status($id), static::$post_status)) return false;

        }

        if (static::$post_slug && (get_post_field('post_name', $id) !== static::$post_slug)) {

            if (!static::$post_slug_deep) return false;

            $ancestor_slugs = array_map(fn ($aid) => get_post_field('post_name', $aid), get_post_ancestors($id));
            if (!in_array(static::$post_slug, $ancestor_slugs, true)) return false;

        }

        if (static::$post_context) {

            $key = static::$context_options[static::$post_context] ?? null;
            if (!$key) return false;

            $expected = (int) get_option($key);
            if (!$expected || ((int) $id !== $expected)) return false;

        }

        return parent::validate_id($id);

    }

    protected static function resolve_term_set ($term, $taxonomy) {

        static $cache = [];
        $key = $taxonomy . ':' . implode(',', (array) $term);
        if (isset($cache[$key])) return $cache[$key];

        $ids = [];

        foreach ((array) $term as $slug) {

            if (!$target = get_term_by('slug', $slug, $taxonomy)) continue;

            $ids[] = (int) $target->term_id;

            foreach ((array) get_term_children($target->term_id, $taxonomy) as $child_id) {
                $ids[] = (int) $child_id;
            }

        }

        return $cache[$key] = array_values(array_unique($ids));

    }

    public static function get_specificity () {

        return (int) (
              ((bool) static::$post_type)
            + ((bool) static::$post_status)  * 10
            + ((bool) static::$term)         * 100
            + ((bool) static::$post_slug)    * 1000
            + ((bool) static::$post_context) * 10000
        );

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

    public static function query ($args = [], &$query = null) {

        global $wp_query;

        $qv = new Query_Vars;

        if (static::is_digitalis_ajax($wp_query)) $qv->merge($wp_query->query_vars);

        $qv->post_type = static::$post_type ?: 'any';

        if (static::$post_status) $qv->post_status = static::$post_status;

        if (static::$term) $qv->add_tax_query([
            'taxonomy' => static::$taxonomy,
            'field'    => is_int(static::$term) ? 'term_id' : 'slug',
            'terms'    => static::$term,
        ]);

        $qv->merge((is_admin() && !wp_doing_ajax()) ? static::get_admin_query_vars($args) : static::get_query_vars($args), true);
        $qv->merge($args, true);

        $query = $qv->make_query();

        $posts = Query_Manager::get_instance()->execute($query, [
            'role' => 'programmatic',
        ]);

        return static::get_instances($posts);

    }

    /**
     * Wraps the global $wp_query->posts as model instances — for consumers
     * that want the page's current loop. Use query() for everything else.
     */
    public static function get_from_main_query (&$query = null) {

        global $wp_query;

        $query = $wp_query;
        return static::get_instances($wp_query->posts ?? []);

    }

    public static function is_main_query ($wp_query) {

        return !wp_doing_ajax() && $wp_query && $wp_query->is_main_query() && static::query_is_post_type($wp_query);

    }

    // Reuse-the-main-loop decision for Archive: true only when the main query is
    // an actual *listing* of this post type. Singular pages pass is_main_query()
    // (the page's one post is of this type) but are not a list to reuse.
    public static function main_query_is_archive ($wp_query) {

        return static::is_main_query($wp_query) && !$wp_query->is_singular();

    }

    public static function is_digitalis_ajax ($wp_query) {
    
        return ($wp_query && $wp_query->get(Post_Type::AJAX_Flag) && static::query_is_post_type($wp_query));
    
    }

    public static function query_is_post_type ($wp_query) {

        return Query_Vars::compare_post_type($wp_query, static::$post_type);

    }

    //

    protected function build_instance ($data) {

        $wp_post     = new WP_Post((object) $data);
        $wp_post->ID = $this->id;

        if (static::$post_type) $wp_post->post_type = static::$post_type;
        
        parent::build_instance($wp_post);

    }

    public function get_global_var () {

        return static::$post_type ? "_" . static::$post_type : false;

    }

    // Data Access

    public function reload () {

        $this->clear_content_cache();
    
        return parent::reload();
    
    }

    public function save ($post_array = [], $fire_after_hooks = true) {

        $post_array = wp_parse_args($post_array, get_object_vars($this->wp_post));

        $tax_input = $post_array['tax_input'] ?? []; // Process the 'tax_input' manually as wp_insert_post check's if there user is allowed to add the tax, which fails for cron. (https://core.trac.wordpress.org/ticket/19373)
        if (isset($post_array['tax_input'])) unset($post_array['tax_input']);

        if ($this->is_new()) {

            if (isset($post_array['ID'])) unset($post_array['ID']);
            $post_id = wp_insert_post($post_array, true, $fire_after_hooks);

        } else {

            $post_array['ID'] = $this->get_id();
            $post_id = wp_update_post($post_array, true, $fire_after_hooks);

        }

        if (is_wp_error($post_id)) return $post_id;

        $this->is_new = false;
        $this->id     = $post_id;

        // Echoing $post_array back would leak meta_input/field_input into the cache for a later save() to replay.
        $this->clear_wp_model_cache();
        $this->hydrate_instance();

        $this->dirty = false;
        $this->cache_instance();
        $this->clear_content_cache();
        $this->unstash();

        if ($tax_input) foreach ($tax_input as $taxonomy => $terms) {

            wp_set_post_terms($post_id, $terms, $taxonomy, $post_array['append_terms'] ?? false);

        }

        if ($post_array['field_input'] ?? false) $this->update_fields($post_array['field_input']);

        // TODO: Add to cache

        return $this;

    }

    public function duplicate ($overrides = [], $exclude_meta = []) {

        $wp_post = $this->get_wp_post();

        kses_remove_filters(); // Preserve block delimiters for users without unfiltered_html.

        try {

            $duplicate = static::create(wp_parse_args($overrides, [
                'post_type'    => $wp_post->post_type,
                'post_status'  => 'draft',
                'post_title'   => $wp_post->post_title,
                'post_content' => $wp_post->post_content,
                'post_excerpt' => $wp_post->post_excerpt,
                'post_author'  => $wp_post->post_author,
            ]));

            $result = $duplicate->save();

        } finally {

            kses_init_filters();

        }

        if (is_wp_error($result)) return $result;

        $excluded = array_merge(
            ['_edit_lock', '_edit_last', '_wp_old_slug'],
            (array) $exclude_meta,
            (array) apply_filters('lattice.post.duplicate.exclude_meta', [], $this)
        );

        foreach (get_post_meta($this->get_id()) as $key => $values) {

            if (in_array($key, $excluded, true)) continue;

            foreach ($values as $value) $duplicate->add_meta($key, maybe_unserialize($value));

        }

        foreach (get_object_taxonomies($wp_post->post_type) as $taxonomy) {

            $term_ids = wp_get_object_terms($this->get_id(), $taxonomy, ['fields' => 'ids']);

            if (!is_wp_error($term_ids) && $term_ids) wp_set_object_terms($duplicate->get_id(), $term_ids, $taxonomy);

        }

        return $duplicate;

    }

    public function delete ($force_delete = false) {

        // TODO: delete this object? null the cache?

        return wp_delete_post($this->wp_post->ID, $force_delete);

    }

    public function get_parent () {

        return ($parent_id = $this->get_parent_id()) ? Post::get_instance($parent_id) : null;

    }

}