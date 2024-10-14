<?php

namespace Digitalis;

use stdClass;
use WP_Post;
use WP_Query;

class Post extends Model {

    protected static $post_type       = false;       // string           - Validate by post_type. Leave false to allow any generic post type.
    protected static $post_status     = false;       // string|int|array - Validate by post_status. Leave false to allow any status.
    protected static $term            = false;       // string|int|array - Validate by taxonomy term. Leave false to allow any term.
    protected static $taxonomy        = 'category';  // string           - Taxonomy to validate term against.

    protected static $post_type_class = false;       // (deprecated) Used when querying the model to get retrieve query vars.

    public static function process_data (&$data) {

        if (is_array($data)) $data = (object) $data;

    }

    public static function extract_id ($data = null) {

        global $post;

        if (is_null($data) && $post)   return $post->ID;
        if ($data instanceof WP_Post)  return $data->ID;
        if ($data instanceof stdClass) return 'new';
        if ($data == 'new')            return 'new';

        return (int) parent::extract_id($data);

    }

    public static function extract_uid ($id, $data = null) {

        if ($id == 'new') return random_int(1000000000, PHP_INT_MAX);

        return parent::extract_uid($id, $data);

    }

    public static function validate_id ($id) {

        if ($id == 'new')                                                        return true;
        if (static::$post_type && (get_post_type($id) != static::$post_type))    return false;
        if (static::$term && (!has_term(static::$term, static::$taxonomy, $id))) return false;

        if (static::$post_status) {

            if (!is_array(static::$post_status)) static::$post_status = [static::$post_status];
            if (!in_array(get_post_status($id), static::$post_status)) return false;

        }

        return (is_int($id) && ($id > 0));

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

            $query->set_var('post_type', static::$post_type);
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

    public function __construct ($data = null, $uid = null, $id = null) {

        parent::__construct($data, $uid, $id);

        if ($this->id == 'new') {

            if (!is_object($this->data)) $this->data = new stdClass();

            $this->data->ID           = $this->uid;
            $this->data->post_type    = static::$post_type;
            if (!property_exists($this->data, 'post_content')) $this->data->post_content = '';

            $this->set_post('new', $this->data);

        } else {

            $this->set_post($this->id);

        }

    }

    public function get_global_var () {

        return "_" . static::$post_type;

    }

    //

    protected $post_id;
    protected $wp_post;

    protected function set_post ($post, $data = null) {

        $post_id = false;

        if (is_int($post))                { $post_id = $post;       }
        elseif (is_string($post))         { $post_id = (int) $post; }
        elseif ($post instanceof WP_Post) { $post_id = $post->ID;   }

        if ($post_id) {

            $this->post_id = $post_id;
            $this->wp_post = WP_Post::get_instance($post_id);

            $this->id = $post_id;

        } elseif ($post == 'new' && ($data instanceof stdClass)) {

            $this->post_id = $post;
            $this->wp_post = new WP_Post($data);

            wp_cache_set($data->ID, $this->wp_post, 'posts');

            $this->id = $post_id;

        }

    }

    public function get_id() {

        return $this->wp_post->ID; // Pass the id directly from the wp_post instance to handle new posts. ($this->wp_post->ID = random integer, $this->post_id = 'new')

    }

    public function get_post () {

        return $this->wp_post;

    }

    public function is_new () {
    
        return ($this->post_id == 'new');
    
    }

    // Access Methods

    public function get_slug () {
        
        return $this->wp_post->post_name;
        
    }

    public function get_guid () {

        return get_the_guid($this->wp_post);

    }

    public function get_classes ($class = '') {

        return get_post_class($class, $this->wp_post);

    }

    public function get_title () {

        return get_the_title($this->wp_post);

    }

    protected $content;

    public function get_content ($apply_filters = true, $more_link_text = null, $strip_teaser = false) {

        if (is_null($this->content)) {

            $this->content = get_the_content($more_link_text, $strip_teaser, $this->wp_post);
            if ($apply_filters) $this->content = apply_filters('the_content', $this->content);

        }

        return $this->content;

    }

    public function get_excerpt ($force_trim = false) {

        $excerpt = get_the_excerpt($this->wp_post);

        if ($force_trim) $excerpt = wp_trim_words($excerpt, apply_filters('excerpt_length', (int) _x('55', 'excerpt_length')), apply_filters('excerpt_more', ' ' . '[&hellip;]'));

        return $excerpt;

    }

    public function get_status () {

        return get_post_status($this->wp_post);

    }

    public function is_sticky () {

        return is_sticky($this->wp_post->ID);

    }

    // Type

    public function get_type () {

        return get_post_type($this->wp_post);

    }

    public function get_post_type_object () {

        return get_post_type_object($this->get_type());

    }

    public function get_type_object () {

        return $this->get_post_type_object();

    }

    // URLs

    public function get_permalink ($leavename = false) {

        return get_permalink($this->wp_post, $leavename);

    }

    public function get_url ($leavename = false) {

        return $this->get_permalink($leavename);

    }

    public function get_edit_url ($context = false) {

        return get_edit_post_link($this->wp_post, $context);

    }

    public function get_archive_url () {
    
        return get_post_type_archive_link($this->get_type());
    
    }

    // Dates

    public function get_date ($format = '') {

        return get_the_date($format, $this->wp_post);

    }

    public function get_date_modified ($format = '') {

        return get_the_modified_date($format, $this->wp_post);

    }

    public function get_post_datetime ($field = 'date', $source = 'local') {

        return get_post_datetime($this->wp_post, $field, $source);

    }

    public function get_post_time ($format = 'U', $gmt = false, $translate = false) {

        return get_post_time($format, $gmt, $this->wp_post, $translate);

    }

    public function get_time_modified ($format = 'U', $gmt = false, $translate = false) {

        return get_post_modified_time($format, $gmt, $this->wp_post, $translate);

    }

    public function get_post_timestamp ($field = 'date') {

        return get_post_timestamp($this->wp_post, $field);

    }

    // Author

    public function get_author_id () {

        return (int) $this->wp_post->post_author;

    }

    public function get_author_model () {

        return User::get_instance($this->get_author_id());

    }

    // Attachments

    public function get_attached_media ($post_mime_type = '') {

        return get_attached_media($post_mime_type, $this->wp_post->ID);

    }

    public function get_attachments ($post_mime_type = '') {
    
        $models = [];

        if ($attachments = $this->get_attached_media($post_mime_type)) foreach ($attachments as $attachment) {
        
            if ($model = Attachment::get_instance($attachment)) $models[] = $model;
        
        }

        return $models;
    
    }

    public function has_thumbnail () {
        
        return has_post_thumbnail($this->wp_post->ID);
        
    }

    public function has_image () {
        
        return $this->has_thumbnail();
        
    }

    public function get_thumbnail ($size = 'post-thumbnail', $attr = '') {

        return get_the_post_thumbnail($this->wp_post->ID, $size, $attr);

    }

    public function get_image ($size = 'post-thumbnail', $attr = '') {

        return $this->get_thumbnail($size, $attr);

    }

    public function get_thumbnail_url ($size = 'post-thumbnail') {

        return get_the_post_thumbnail_url($this->wp_post->ID, $size);

    }

    public function get_image_url ($size = 'post-thumbnail') {

        return $this->get_thumbnail_url($size);

    }

    public function get_thumbnail_id () {
        
        return get_post_thumbnail_id($this->wp_post->ID);
        
    }

    public function get_image_id () {
        
        return $this->get_thumbnail_id();
        
    }

    // Terms

    public function get_terms ($taxonomy) {

        return get_the_terms($this->wp_post->ID, $taxonomy);

    }

    public function has_term ($term = '', $taxonomy = '') {
        
        return has_term($term, $taxonomy, $this->wp_post->ID);
        
    }

    public function set_terms ($terms, $taxonomy = 'post_tag', $append = false) {
    
        return wp_set_post_terms($this->wp_post->ID, $terms, $taxonomy, $append);
    
    }

    public function add_terms ($terms, $taxonomy = 'post_tag') {
    
        return $this->set_terms($terms, $taxonomy, true);
    
    }

    public function remove_terms ($terms, $taxonomy = 'post_tag') {
    
        return wp_remove_object_terms($this->wp_post->ID, $terms, $taxonomy);
    
    }

    // Post Parent

    public function has_post_parent () {
        
        return has_post_parent($this->wp_post);
        
    }

    public function get_post_parent () {
        
        return get_post_parent($this->wp_post);
        
    }

    public function get_parent_id () {
    
        return ($parent = $this->get_post_parent()) ? $parent->ID : null;
    
    }

    public function get_parent () {
    
        return ($parent_id = $this->get_parent_id()) ? static::get_instance($parent_id) : null;
    
    }

    // Password Protection

    public function is_password_protected () {

        return post_password_required($this->wp_post);

    }

    public function get_password_form () {
        
        return get_the_password_form($this->wp_post);
        
    }

    // Meta

    public function get_meta ($key = '', $single = false) {

        return get_post_meta($this->wp_post->ID, $key, $single);

    }

    public function add_meta ($key, $value, $unique = false) {

        return $this->is_new() ? false : add_post_meta($this->wp_post->ID, $key, $value, $unique);

    }

    public function update_meta ($key, $value, $prev_value = '') {

        return $this->is_new() ? false : update_post_meta($this->wp_post->ID, $key, $value, $prev_value);

    }

    public function update_metas ($data) {
    
        if ($data) foreach ($data as $key => $value) $this->update_meta($key, $value);
    
    }

    // ACF Methods

    public function get_field ($selector, $format_value = true) {

        return get_field($selector, $this->wp_post->ID, $format_value);

    }

    public function esc_field ($selector, $format_value = true) {

        return trim(esc_attr($this->get_field($selector, $format_value)));

    }

    public function update_field ($selector, $value) {

        return $this->is_new() ? false : update_field($selector, $value, $this->wp_post->ID);
        
    }

    public function update_fields ($data) {
    
        if ($data) foreach ($data as $selector => $value) $this->update_field($selector, $value);
    
    }

    // CRUD Methods

    public function reload () {
    
        $this->wp_post = get_post($this->get_id());
    
    }

    public function save ($post_array = [], $fire_after_hooks = true) {

        $post_array = wp_parse_args($post_array, get_object_vars($this->wp_post));

        if ($this->is_new() && isset($post_array['ID'])) unset($post_array['ID']);

        //dprint("post_array");
        //dprint($post_array);

        $tax_input = $post_array['tax_input'] ?? []; // We need to process the 'tax_input' manually as wp_insert_post check's if there user is allowed to add the tax, which fails for cron. (https://core.trac.wordpress.org/ticket/19373)
        $post_array['tax_input'] = '';

        if ($this->post_id == 'new') {

            $post_id = wp_insert_post($post_array, true, $fire_after_hooks);

        } else {

            $post_array['ID'] = $this->get_id();
            $post_id = wp_update_post($post_array, true, $fire_after_hooks);

        }

        if ($post_id && !is_wp_error($post_id)) {

            if ($tax_input) foreach ($tax_input as $taxonomy => $terms) {

                wp_set_post_terms($post_id, $terms, $taxonomy, $post_array['append_terms'] ?? false);

            }

            if ($post_array['field_input'] ?? []) foreach ($post_array['field_input'] as $selector => $value) {
            
                update_field($selector, $value, $post_id);
            
            }

            $this->post_id     = $post_id;
            $this->wp_post->ID = $post_id;
            $this->id          = $post_id;

        } 

        return $post_id;

    }

    public function delete ($force_delete = false) {
    
        return wp_delete_post($this->wp_post->ID, $force_delete);
    
    }

}