<?php

namespace Digitalis;

use stdClass;
use WP_Term;
use WP_Term_Query;
use WP_Error;

trait Has_WP_Term {

    protected $wp_term;

    protected function init_term ($term, $data = null) {

        $term_id = false;

        if (is_int($term))                { $term_id = $term;          }
        elseif (is_string($term))         { $term_id = (int) $term;    }
        elseif ($term instanceof WP_Term) { $term_id = $term->term_id; }

        if ($term_id) {

            $this->is_new  = false;
            $this->wp_term = $term instanceof WP_Term ? $term : WP_Term::get_instance($term_id, static::$taxonomy);
            $this->id      = $term_id;

        } elseif ($term == 'new' && ($data instanceof stdClass)) {

            $this->is_new  = true;
            $this->wp_term = new WP_Term($data);

            wp_cache_set($data->term_id, $this->wp_term, 'terms');

            $this->id = $term_id;

        }

    }

    public function get_wp_term () {

        return $this->wp_term;

    }

    public function set_wp_term ($wp_term) {

        $this->wp_term = $wp_term;
        return $this;

    }

    public function get_id() {

        return $this->wp_term->term_id; // Pass the id directly from the wp_term instance to handle new terms. ($this->wp_term->ID = random integer)

    }

    public function is_new () {
    
        return $this->is_new;
    
    }

    public function get_children () {

        // Where'd you go?
    
        return $this->children;
    
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

    // Meta

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

}