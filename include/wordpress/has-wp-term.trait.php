<?php

namespace Digitalis;

use stdClass;
use WP_Term;

trait Has_WP_Term {

    protected $wp_term;

    protected function init_wp_model ($data) {

        if (is_int($data)) {

            $this->set_wp_term(WP_Term::get_instance($data));

        } else if ($data instanceof WP_Term) {

            $this->set_wp_term($data);

        } else {

            $this->set_wp_term(new WP_Term($data));
            //if ($this->uid) wp_cache_set($this->uid, $this->get_wp_post(), 'posts');

        }

    }

    public function get_wp_term () {

        return $this->wp_term;

    }

    public function set_wp_term ($wp_term) {

        $this->wp_term = $wp_term;
        return $this;

    }

    /* public function get_id() {

        return $this->wp_term->term_id; // Pass the id directly from the wp_term instance to handle new terms. ($this->wp_term->ID = random integer)

    } */

    public function get_children () {

        // Where'd you go?
    
        return $this->children;
    
    }

    //

    public function get_slug () {

        return $this->wp_term->slug;

    }

    public function set_slug ($slug) {

        $this->wp_term->slug = $slug;
        return $this;

    }

    public function get_name () {

        return $this->wp_term->name;

    }

    public function set_name ($name) {

        $this->wp_term->name = $name;
        return $this;

    }

    public function get_term_group () {

        return $this->wp_term->term_group;

    }

    public function set_term_group ($term_group) {

        $this->wp_term->term_group = $term_group;
        return $this;

    }

    public function get_term_taxonomy_id () {

        return $this->wp_term->term_taxonomy_id;

    }

    public function set_term_taxonomy_id ($term_taxonomy_id) {

        $this->wp_term->term_taxonomy_id = $term_taxonomy_id;
        return $this;

    }

    public function get_taxonomy () {

        return $this->wp_term->taxonomy;

    }

    public function set_taxonomy ($taxonomy) {

        $this->wp_term->taxonomy = $taxonomy;
        return $this;

    }

    public function get_description () {

        return $this->wp_term->description;

    }

    public function set_description ($description) {

        $this->wp_term->description = $description;
        return $this;

    }

    public function get_parent_id () {

        return $this->wp_term->parent;

    }

    public function set_parent_id ($parent_id) {

        $this->wp_term->parent = $parent_id;
        return $this;

    }

    public function get_parent () {

        return static::get_instance($this->get_parent_id());

    }

    public function set_parent ($parent) {

        if ($id = static::extract_id($parent)) $this->set_parent_id($id);
        return $this;

    }

    public function get_all_parents ($asc = true) {

        $parents = [];
        $parent  = $this;
    
        while ($parent = $parent->get_parent()) $parents[] = $parent;

        if (!$asc) $parents = array_reverse($parents);

        return $parents;
    
    }

    //

    public function get_count () {

        return $this->wp_term->count;

    }

    public function get_url () {

        return get_term_link($this->wp_term->term_id);

    }

    public function get_feed ($feed = '') {
    
        return get_term_feed_link($this->wp_term->term_id, '', $feed);
    
    }

    // Meta

    public function get_meta ($key, $single = true) {

        return $this->is_new() ? false : get_term_meta($this->wp_term->term_id, $key, $single);

    }

    public function add_meta ($key, $value, $unique = false) {

        return $this->is_new() ? false : add_term_meta($this->wp_term->term_id, $key, $value, $unique);

    }

    public function update_meta ($key, $value, $prev_value = '') {

        return $this->is_new() ? false : update_term_meta($this->wp_term->term_id, $key, $value, $prev_value);

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