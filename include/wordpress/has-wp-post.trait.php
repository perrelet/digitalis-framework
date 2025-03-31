<?php

namespace Digitalis;

use stdClass;
use WP_Post;

trait Has_WP_Post {

    protected $wp_post;

    protected function init_wp_model ($data) {

        if (is_int($data)) {

            $this->set_wp_post(WP_Post::get_instance($data));

        } else if ($data instanceof WP_Post) {

            $this->set_wp_post($data);

        } else {

            $this->set_wp_post(new WP_Post($data));
            //if ($this->uid) wp_cache_set($this->uid, $this->get_wp_post(), 'posts');

        }

    }

    /* public function get_id() {

        return $this->wp_post->ID; // Pass the id directly from the wp_post instance to handle new posts. ($this->wp_post->ID = random integer)

    } */

    public function get_wp_post () {

        return $this->wp_post;

    }

    public function set_wp_post ($wp_post) {

        $this->wp_post = $wp_post;
        return $this;

    }

    // Access Methods

    public function get_slug () {
        
        return $this->wp_post->post_name;
        
    }

    public function set_slug ($slug) {
        
        $this->wp_post->post_name = $slug;
        return $this;
        
    }

    public function get_guid () {

        return get_the_guid($this->wp_post);

    }

    public function set_guid ($guid) {
        
        $this->wp_post->guid = $guid;
        return $this;
        
    }

    public function get_classes ($class = '') {

        return get_post_class($class, $this->wp_post);

    }

    public function get_title () {

        return get_the_title($this->wp_post);

    }

    public function set_title ($title) {
    
        $this->wp_post->post_title = $title;
        return $this;
    
    }

    protected $content_cache = [];

    public function get_content ($apply_filters = true, $more_link_text = null, $strip_teaser = false) {

        $key = serialize(array_values(func_get_args()));

        if (!isset($this->content_cache[$key])) {

            $content = get_the_content($more_link_text, $strip_teaser, $this->wp_post);
            if ($apply_filters) $content = apply_filters('the_content', $content);
            $this->content_cache[$key] = $content;

        }

        return $this->content_cache[$key];

    }

    public function set_content ($content) {
    
        $this->wp_post->post_content = $content;
        return $this;
    
    }

    public function get_excerpt ($force_trim = false) {

        $excerpt = get_the_excerpt($this->wp_post);

        if ($force_trim) $excerpt = wp_trim_words($excerpt, apply_filters('excerpt_length', (int) _x('55', 'excerpt_length')), apply_filters('excerpt_more', ' ' . '[&hellip;]'));

        return $excerpt;

    }

    public function set_excerpt ($excerpt) {
    
        $this->wp_post->post_excerpt = $excerpt;
        return $this;
    
    }

    public function get_status () {

        return get_post_status($this->wp_post);

    }

    public function set_status ($status) {
    
        $this->wp_post->post_status = $status;
        return $this;
    
    }

    public function is_sticky () {

        return is_sticky($this->wp_post->ID);

    }

    // Type

    public function get_type () { // REFACTOR get_post_type()

        return $this->wp_post->post_type;

    }

    public function set_post_type ($post_type) {
    
        $this->wp_post->post_type = $post_type;
        return $this;
    
    }

    public function get_post_type_object () {

        return get_post_type_object($this->get_type());

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

    public function set_date ($date) {
    
        $this->wp_post->post_date = $date;
        return $this;
    
    }

    public function set_date_gmt ($date) {
    
        $this->wp_post->post_date_gmt = $date;
        return $this;
    
    }

    // Author

    public function get_author_id () {

        return (int) $this->wp_post->post_author;

    }

    public function set_author_id ($author_id) {
    
        $this->wp_post->post_author = (int) $author_id;
        return $this;
    
    }

    public function get_author ($auto_resolve = true) {

        return User::get_instance($this->get_author_id(), $auto_resolve);

    }

    public function set_author ($user) {
    
        if (($user instanceof User) && ($user_id = $user->get_id())) $this->set_author_id($user_id);
        return $this;
    
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

    public function get_terms ($tax_or_term) {

        if (is_subclass_of($tax_or_term, Term::class)) {

            return Call::static($tax_or_term, 'query_post', $this);

        } else {

            return get_the_terms($this->wp_post->ID, $tax_or_term);

        }

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

    public function set_parent_id ($parent_id) {
    
        $this->wp_post->post_parent = (int) $parent_id;
        return $this;
    
    }

    public function get_parent () {
    
        return ($parent_id = $this->get_parent_id()) ? static::get_instance($parent_id) : null;
    
    }

    public function set_parent ($post) {
    
        if (($post instanceof Post) && ($post_id = $post->get_id())) $this->set_parent_id($post_id);
        return $this;
    
    }

    // Password Protection

    public function is_password_protected () {

        return post_password_required($this->wp_post);

    }

    public function get_password_form () {
        
        return get_the_password_form($this->wp_post);
        
    }

    public function set_password ($password) {
    
        $this->wp_post->post_password = $password;
        return $this;
    
    }

    // Meta

    public function get_meta ($key = '', $single = false) {

        return $this->is_new() ? false : get_post_meta($this->wp_post->ID, $key, $single);

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

    public function delete_meta ($key, $value = '') {
    
        return $this->is_new() ? false : delete_post_meta($this->wp_post->ID, $key, $value);
    
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

}