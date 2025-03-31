<?php

namespace Digitalis;

class Revision extends Post {

    protected static $post_type = 'revision';

    public function is_autosave () {

        return wp_is_post_autosave($this->wp_post);

    }

    public function restore_post ($fields = null) {

        return $this->is_new() ? null : wp_restore_post_revision($this->wp_post->ID, $fields);

    }

    public function restore_post_meta ($post_id = null) {

        if (is_null($post_id)) $post_id = $this->get_parent_id();

        return $this->is_new() ? null : wp_restore_post_revision_meta($post_id, $this->wp_post->ID);

    }

    // TODO: wp_delete_post_revision

}