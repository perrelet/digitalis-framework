<?php

namespace Digitalis;

use WP_Comment;

class Comment extends WP_Model {

    use Has_WP_Comment;

    public static function extract_id ($data = null) {

        if (is_object($data) && property_exists($data, 'comment_ID')) return $data->comment_ID;
        if (is_object($data) && method_exists($data, 'get_id'))       return $data->get_id();

        return (int) parent::extract_id($data);

    }

    public static function validate_id ($id) {

        return parent::validate_id($id);

    }

    //

    protected function build_instance ($data) {

        $wp_comment             = new WP_Comment((object) $data);
        $wp_comment->comment_ID = $this->id;

        $this->init_wp_model($wp_comment);

        parent::build_instance($data);

    }

}