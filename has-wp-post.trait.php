<?php

namespace Digitalis;

use \WP_Post;

trait Has_WP_Post {

    protected $post_id;
    protected $wp_post;

    public function set_post ($post_id) {

        if ($post_id instanceof WP_Post) $post_id = $post_id->ID;

        $this->post_id = $post_id;
        $this->wp_post = WP_Post::get_instance($post_id);

    }

    public function get_id() {

        return $this->post_id;

    }

    public function get_post () {

        return $this->wp_post;

    }

    //

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

    public function get_meta ($key = '', $single = false) {

        return get_post_meta($this->post_id, $key, $single);

    }

    public function get_permalink ($leavename = false) {

        return get_permalink($this->wp_post, $leavename);

    }

    public function get_post_datetime ($field = 'date', $source = 'local') {

        return get_post_datetime($this->wp_post, $field, $source);

    }

    public function get_time ($format = 'U', $gmt = false, $translate = false) {

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

}