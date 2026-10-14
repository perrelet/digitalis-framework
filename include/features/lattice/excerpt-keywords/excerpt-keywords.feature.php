<?php

namespace Digitalis;

require_once __DIR__ . '/has-keywords.trait.php';

class Excerpt_Keywords extends Feature {

    public function __construct () {

        $this->add_action('wp_after_insert_post', 'maybe_update_keywords');
        $this->add_action('lattice.relationship.partner_updated', 'update_partner_keywords');

    }

    public function maybe_update_keywords ($post_id, $post, $update, $post_before) {

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;

        $this->update_keywords_for($post_id);

    }

    public function update_partner_keywords ($type, $id) {

        if ($type !== 'post') return;

        $this->update_keywords_for($id);

    }

    protected function update_keywords_for ($post_id) {

        if (!$instance = Post::get_instance($post_id))    return;
        if (!method_exists($instance, 'update_keywords')) return;

        $instance->update_keywords();

    }

}
