<?php

namespace Digitalis;

require_once __DIR__ . '/has-keywords.trait.php';

class Excerpt_Keywords extends Feature {

    public function __construct () {

        $this->add_action('wp_after_insert_post', 'maybe_update_keywords');

    }

    public function maybe_update_keywords ($post_id, $post, $update, $post_before) {

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;

        if (!$instance = Post::get_instance($post_id))    return;
        if (!method_exists($instance, 'update_keywords')) return;

        $instance->update_keywords();

    }

}
