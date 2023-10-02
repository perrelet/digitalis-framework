<?php

namespace Digitalis;

use \WP_Post;
use \WP_Query;

abstract class Post extends Model {

    use Has_WP_Post;

    protected static $post_type = 'post';           // Override me.
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

    public static function get_query_vars () {

        $call = static::$post_type_class . "::get_query_vars";
        return is_callable($call) ? call_user_func($call) : [];

    }

    public static function get_admin_query_vars () {

        $call = static::$post_type_class . "::get_admin_query_vars";
        return is_callable($call) ? call_user_func($call) : [];

    }

    public static function query ($args = [], &$query = null, $skip_main = false) {

        global $wp_query;

        $instances      = [];
        $posts          = [];
        $this_post_type = false;

        if ($wp_query && ($queried_post_type = $wp_query->get('post_type'))) {

            if (is_array($queried_post_type)) {

                if (in_array('any', $queried_post_type) || in_array(static::$post_type, $queried_post_type)) $this_post_type = true;

            } else {

                if (($queried_post_type == 'any') || ($queried_post_type == static::$post_type)) $this_post_type = true;

            }

        } elseif ((static::$post_type == 'post') && $wp_query) {

            $this_post_type = $wp_query->is_posts_page;

        }

        if (!$skip_main && $wp_query && $wp_query->is_main_query() && $this_post_type) {

            $query = $wp_query;
            $posts = $wp_query->posts;

        } else {

            /* if (!$skip_main && $wp_query->query_vars) $args = wp_parse_args($args, $wp_query->query_vars);
            $args = static::get_query_vars($args);
            $args['post_type'] = static::$post_type;
            $posts = (new WP_Query($args))->posts; */

            //if (!$skip_main && $this_post_type && $wp_query->query_vars) $args = merge_query_vars($args, $wp_query->query_vars); // ?
            /* $args = merge_query_vars([
                'post_type' => static::$post_type,
            ], $args);
            $args = merge_query_vars($args, is_admin() ? static::get_admin_query_vars() : static::get_query_vars());
            $posts = (new WP_Query($args))->posts; */

            //

            $query = new Digitalis_Query();

            if (!$skip_main && $wp_query) $query->merge($wp_query->query_vars);

            $query->set_var('post_type', static::$post_type);
            $query->merge((is_admin() && !wp_doing_ajax()) ? static::get_admin_query_vars() : static::get_query_vars());
            $query->merge($args);
            $posts = $query->query();

        }

        return static::get_instances($posts);

    }

    //

    public function __construct ($post_id) {

        $this->set_post($post_id);

        parent::__construct($post_id);

    }

    public function get_global_var () {

        return "_" . static::$post_type;

    }

}