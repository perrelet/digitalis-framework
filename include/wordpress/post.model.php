<?php

namespace Digitalis;

use \WP_Post;
use \WP_Query;

abstract class Post extends Model {

    use Has_WP_Post;

    protected static $post_type = 'post';                           // Override me.
    protected static $post_type_class = false;      // Override me - Used when querying the model. With this we can get the main query args from the CPT.

    public function is_post () { return true; }

    public static function extract_id ($id = null) {

        global $post;

        if (is_null($id) && $post)     return $post->ID;
        if ($id instanceof WP_Post)    return $id->ID;

        return parent::extract_id($id);

    }

    public static function validate_id ($id) {

        return (!static::$post_type || (get_post_type($id) == static::$post_type));

    } 

    public static function get_query_args ($args = []) {

        $call = static::$post_type_class . "::get_query_args";

        if (is_callable($call)) {

            return call_user_func($call, $args);

        } else {

            return $args;

        }

    }

    public static function query ($args = [], &$query = null, $skip_main = false) {

        global $wp_query;

        $instances = [];
        $posts = [];

        if (!$skip_main && $wp_query && $wp_query->is_main_query() && ($wp_query->get('post_type') === static::$post_type)) {

            $query = $wp_query;
            $posts = $wp_query->posts;

        } else {

            if (!$skip_main && $wp_query->query_vars) $args = wp_parse_args($args, $wp_query->query_vars);
            $args = static::get_query_args($args);
            $args['post_type'] = static::$post_type;
            $query = new WP_Query($args);
            $posts = $query->get_posts();

        }

        return static::get_instances($posts);

    }

    //

    public function __construct ($post_id) {

        $this->set_post($post_id);

        parent::__construct($post_id);

    }


}