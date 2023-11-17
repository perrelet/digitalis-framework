<?php

namespace Digitalis;

use \stdClass;
use \WP_Post;
use \WP_Query;

abstract class Post extends Model {

    use Has_WP_Post;

    protected static $post_type = 'post';           // Override me.
    protected static $post_type_class = false;      // Override me - Used when querying the model. With this we can get the main query args from the CPT.

    public function is_post () { return true; }

    public static function extract_id ($data = null) {

        global $post;

        if (is_null($data) && $post)   return $post->ID;
        if ($data instanceof WP_Post)  return $data->ID;
        if ($data instanceof stdClass) return 'new';

        return parent::extract_id($data);

    }

    public static function extract_uid ($id, $data = null) {

        if ($id == 'new') return random_int(10000000, PHP_INT_MAX);

        return parent::extract_uid($id, $data);

    }

    public static function validate_id ($id) {

        return (!static::$post_type || (get_post_type($id) == static::$post_type) || ($id == 'new'));

    }

    //

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

        $instances = [];
        $posts     = [];

        if (!$skip_main && !wp_doing_ajax() && $wp_query && $wp_query->is_main_query() && static::query_is_post_type($wp_query)) {

            // Use the existing global wp_query.

            $query = $wp_query;
            $posts = $wp_query->posts;

        } else {

            // Build a fresh wp_query.

            $query = new Digitalis_Query();

            if (!$skip_main && $wp_query && $wp_query->is_main_query() && Digitalis_Query::is_multiple($query)) $query->merge($wp_query->query_vars);

            $query->set_var('post_type', static::$post_type);
            $query->merge((is_admin() && !wp_doing_ajax()) ? static::get_admin_query_vars() : static::get_query_vars());
            $query->merge($args);

            $posts = $query->query();

        }

        return static::get_instances($posts);

    }

    protected static function query_is_post_type ($wp_query) {

        return Digitalis_Query::compare_post_type($wp_query, static::$post_type);

    }

    //

    public function __construct ($post_id, $uid, $data) {

        parent::__construct($post_id, $uid, $data);

        if ($post_id == 'new') {

            if ($data instanceof stdClass) $data->ID = $this->uid;

            $this->set_post($post_id, $data);

        } else {

            $this->set_post($post_id);

        }

    }

    public function get_global_var () {

        return "_" . static::$post_type;

    }

}