<?php

namespace Digitalis;

use \stdClass;
use \WP_Post;

trait Has_WP_Post { // Refactor: Consider merging directly into post.model.php (when would we ever need this elsewhere?)

    protected $id;
    protected $post_id;
    protected $wp_post;

    protected function is_post () { return true; } // Override as required

    public function set_post ($post, $data = null) {

        $post_id = false;

        if (is_int($post))                { $post_id = $post;       }
        elseif (is_string($post))         { $post_id = (int) $post; }
        elseif ($post instanceof WP_Post) { $post_id = $post->ID;   }

        if ($post_id) {

            $this->post_id = $post_id;
            $this->wp_post = WP_Post::get_instance($post_id);

            if ($this->is_post()) $this->id = $post_id;

        } elseif ($post == 'new' && ($data instanceof stdClass)) {

            $this->post_id = $post;
            $this->wp_post = new WP_Post($data);

            wp_cache_set($data->ID, $this->wp_post, 'posts');

            if ($this->is_post()) $this->id = $post_id;

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

    public function get_author_id () {

        return $this->wp_post->post_author;

    }

    public function get_author_model () {

        return User::get_instance($this->get_author_id());

    }

    public function get_classes ($class = '') {

        return get_post_class($class, $this->wp_post);

    }

    public function get_content ($apply_filters = true, $more_link_text = null, $strip_teaser = false) {

        $content = get_the_content($more_link_text, $strip_teaser, $this->wp_post);

        return $apply_filters ? apply_filters('the_content', $content) : $content;

    }

    public function get_date ($format = '') {

        return get_the_date($format, $this->wp_post);

    }

    public function get_date_modified ($format = '') {

        return get_the_modified_date($format, $this->wp_post);

    }

    public function get_edit_url ($context = false) {

        return get_edit_post_link($this->wp_post, $context);

    }

    public function get_excerpt ($force_trim = false) {

        $excerpt = get_the_excerpt($this->wp_post);

        if ($force_trim) $excerpt = wp_trim_words($excerpt, apply_filters('excerpt_length', (int) _x('55', 'excerpt_length')), apply_filters('excerpt_more', ' ' . '[&hellip;]'));

        return $excerpt;

    }

    public function get_guid () {

        return get_the_guid($this->wp_post);

    }

    public function has_image () {
        
        return has_post_thumbnail($this->wp_post->ID);
        
    }

    public function get_image ($size = 'post-thumbnail', $attr = '') {

        return get_the_post_thumbnail($this->wp_post->ID, $size, $attr);

    }

    public function get_image_url ($size = 'post-thumbnail') {

        return get_the_post_thumbnail_url($this->wp_post->ID, $size);

    }

    public function get_image_id () {
        
        return get_post_thumbnail_id($this->wp_post->ID);
        
    }

    public function get_attachments ($post_mime_type) {

        return get_attached_media($post_mime_type, $this->wp_post->ID);

    }

    public function get_meta ($key = '', $single = false) {

        return get_post_meta($this->wp_post->ID, $key, $single);

    }

    public function update_meta ($key, $value, $prev_value = '') {

        return $this->is_new() ? false : update_post_meta($this->wp_post->ID, $key, $value, $prev_value);

    }

    public function update_metas ($data) {
    
        if ($data) foreach ($data as $key => $value) $this->update_meta($key, $value);
    
    }

    public function get_permalink ($leavename = false) {

        return get_permalink($this->wp_post, $leavename);

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

    public function get_timestamp ($field = 'date') {

        return get_post_timestamp($this->wp_post, $field);

    }

    public function get_title () {

        return get_the_title($this->wp_post);

    }

    public function get_type () {

        return get_post_type($this->wp_post);

    }

    public function get_post_type_object () {

        return get_post_type_object($this->get_type());

    }

    public function get_type_object () {

        return $this->get_post_type_object();

    }

    public function get_status () {

        return get_post_status($this->wp_post);

    }

    public function get_url ($leavename = false) {

        return $this->get_permalink($leavename);

    }

    public function get_archive_url () {
    
        return get_post_type_archive_link($this->get_type());
    
    }

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

    public function is_sticky () {

        return is_sticky($this->wp_post->ID);

    }

    public function has_post_parent () {
        
        return has_post_parent($this->wp_post);
        
    }

    public function get_post_parent () {
        
        return get_post_parent($this->wp_post);
        
    }

    // Password Protection

    public function is_password_protected () {

        return post_password_required($this->wp_post);

    }

    public function get_password_form () {
        
        return get_the_password_form($this->wp_post);
        
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

            if ($this->is_post()) $this->id = $post_id;

        } 

        return $post_id;

    }

    public function delete ($force_delete = false) {
    
        return wp_delete_post($this->wp_post->ID, $force_delete);
    
    }

}