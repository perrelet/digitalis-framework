<?php

namespace Digitalis;

use \WP_Post;

trait Has_WP_Post {

    protected $id;
    protected $post_id;
    protected $wp_post;

    protected function is_post () { return true; } // Override as required

    public function set_post ($post_id) {

        if ($post_id instanceof WP_Post) $post_id = $post_id->ID;

        $this->post_id = $post_id;
        if ($this->is_post()) $this->id = $post_id;
        $this->wp_post = WP_Post::get_instance($post_id);

    }

    public function get_id() {

        return $this->post_id;

    }

    public function get_post () {

        return $this->wp_post;

    }

    //

    public function get_slug () {
        
        return $this->wp_post->post_name;
        
    }

    public function get_author_id () {

        return $this->wp_post->post_author;

    }

    public function get_classes ($class = '') {

        return get_post_class($class, $this->wp_post);

    }

    public function get_content ($more_link_text = null, $strip_teaser = false) {

        return get_the_content($more_link_text, $strip_teaser, $this->wp_post);

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

    public function get_excerpt ($format = '') {

        return get_the_excerpt($this->wp_post);

    }

    public function get_guid () {

        return get_the_guid($this->wp_post);

    }

    public function get_image ($size = 'post-thumbnail', $attr = '') {

        return get_the_post_thumbnail($this->post_id, $size, $attr);

    }

    public function get_image_url ($size = 'post-thumbnail') {

        return get_the_post_thumbnail_url($this->post_id, $size);

    }

    public function get_meta ($key = '', $single = false) {

        return get_post_meta($this->post_id, $key, $single);

    }

    public function update_meta ($key, $value, $prev_value = '') {

        return update_post_meta($this->post_id, $key, $value, $prev_value);

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

    public function get_type_object () {

        return get_post_type_object($this->get_type());

    }

    public function get_status () {

        return get_post_status($this->wp_post);

    }

    public function get_url ($leavename = false) {

        return $this->get_permalink($leavename);

    }

    public function get_terms ($taxonomy) {

        return get_the_terms($this->post_id, $taxonomy);

    }

    public function has_term ($term = '', $taxonomy = '') {
        
        return has_term($term, $taxonomy, $this->post_id);
        
    }

    // ACF

    public function get_field ($selector, $format_value = true) {

        return get_field($selector, $this->post_id, $format_value);

    }

    public function esc_field ($selector, $format_value = true) {

        return trim(esc_attr($this->get_field($selector, $format_value)));

    }

    public function update_field ($selector, $value) {

        return update_field($selector, $value, $this->post_id);
        
    }

}