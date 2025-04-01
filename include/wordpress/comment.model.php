<?php

namespace Digitalis;

use WP_Comment;

class Comment extends Model {

    use Has_WP_Comment;

    public static function prepare_data (&$data) {

        if (is_array($data)) $data = (object) $data;

    }

    public static function extract_id ($data = null) {

        if (is_object($data) && property_exists($data, 'comment_ID')) return $data->comment_ID;
        if (is_object($data) && method_exists($data, 'get_id'))       return $data->get_id();

        return (int) parent::extract_id($data);

    }

    public static function validate_id ($id) {

        return (is_int($id) && ($id > 0));

    }

    //

    protected function generate_uuid ($data) {

        return spl_object_id((object) $data) * -1;

    }

    protected function build_instance ($data) {

        $wp_comment             = new WP_Comment((object) $data);
        $wp_comment->comment_ID = $this->id;

        $this->init_wp_model($wp_comment);
        $this->cache_wp_model();

    }

    protected function hydrate_instance () {

        $this->init_wp_model($this->id);

    }

}