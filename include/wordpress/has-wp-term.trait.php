<?php

namespace Digitalis;

use stdClass;
use WP_Term;

trait Has_WP_Term {

    use Has_WP_Model, Has_WP_Meta, Has_ACF_Fields;

    protected $wp_term;

    protected function init_wp_model ($data) {

        if (is_int($data)) {

            $this->set_wp_term(WP_Term::get_instance($data));

        } else if ($data instanceof WP_Term) {

            $this->set_wp_term($data);

        } else {

            $this->set_wp_term(new WP_Term((object) $data));

        }

    }

    public function get_wp_term () {

        return $this->wp_term;

    }

    public function set_wp_term ($wp_term) {

        $this->wp_term = $wp_term;
        return $this;

    }

    public function get_children () {

        // Where'd you go?
    
        return $this->children;
    
    }

    // Traits

    public function get_wp_model () {

        return $this->wp_term;

    }

    public function get_wp_model_id () {

        return $this->wp_term->term_id;

    }

    public function get_wp_cache_group () {

        return 'terms';
    
    }

    public function get_wp_meta_type () {

        return 'term';

    }

    public function get_acf_id () {

        return $this->is_new() ? null : 'term_' . $this->wp_term->term_id;

    }

    // Encapsulation

    public function get_slug () {

        return $this->wp_term->slug;

    }

    public function set_slug ($slug) {

        return $this->set_wp_model_prop('slug', $slug);

    }

    public function get_name () {

        return $this->wp_term->name;

    }

    public function set_name ($name) {

        return $this->set_wp_model_prop('name', $name);

    }

    public function get_term_group () {

        return $this->wp_term->term_group;

    }

    public function set_term_group ($term_group) {

        return $this->set_wp_model_prop('term_group', $term_group);

    }

    public function get_term_taxonomy_id () {

        return $this->wp_term->term_taxonomy_id;

    }

    public function set_term_taxonomy_id ($term_taxonomy_id) {

        return $this->set_wp_model_prop('term_taxonomy_id', $term_taxonomy_id);

    }

    public function get_taxonomy () {

        return $this->wp_term->taxonomy;

    }

    public function set_taxonomy ($taxonomy) {

        return $this->set_wp_model_prop('taxonomy', $taxonomy);

    }

    public function get_description () {

        return $this->wp_term->description;

    }

    public function set_description ($description) {

        return $this->set_wp_model_prop('description', $description);

    }

    public function get_parent_id () {

        return $this->wp_term->parent;

    }

    public function set_parent_id ($parent_id) {

        return $this->set_wp_model_prop('parent', $parent_id);

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

        return get_term_link($this->id);

    }

    public function get_feed ($feed = '') {
    
        return get_term_feed_link($this->id, '', $feed);
    
    }

}