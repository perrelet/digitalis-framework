<?php

declare(strict_types=1);

namespace Lattice;

use WP_Comment;

class Comment extends WP_Model {

    protected static function get_record_rows (int|string $id) : array {

        return [['comments', $id]];

    }

    protected static function get_record_storage () : string {

        return 'comment';

    }

    protected static function get_record_properties () : array {

        return [...parent::get_record_properties(), 'wp_comment'];

    }

    use Has_WP_Comment;

    public static function extract_id (mixed $data = null) {

        if (is_object($data) && property_exists($data, 'comment_ID')) return (int) $data->comment_ID;
        if (is_object($data) && method_exists($data, 'get_id'))       return $data->get_id();

        return (int) parent::extract_id($data);

    }

    public static function validate_id (mixed $id) {

        if (!get_comment($id)) return false;

        return parent::validate_id($id);

    }

    //

    protected function build_instance (mixed $data) {

        $wp_comment             = new WP_Comment((object) $data);
        $wp_comment->comment_ID = $this->id;

        parent::build_instance($wp_comment);

    }

}