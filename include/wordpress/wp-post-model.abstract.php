<?php

namespace Digitalis;

use \WP_Post;
use \WP_Query;

abstract class WP_Post_Model {

    use Has_WP_Post;

    protected static $post_type = 'post'; // Override me.
    
    protected static $instances = [];

    public function is_post () { return true; }

    public static function get_instance ($post_id = null) {

        global $post;

        if (is_null($post_id) && $post)     $post_id = $post->ID;
        if ($post_id instanceof self)       $post_id = $post_id->get_id();
        if ($post_id instanceof WP_Post)    $post_id = $post_id->ID;

        if (is_null($post_id)) return null;

        if (!isset(self::$instances[static::$post_type])) self::$instances[static::$post_type] = [];
        
        if (!isset(self::$instances[static::$post_type][$post_id])) {
            
            if (!static::$post_type || (get_post_type($post_id) == static::$post_type)) {

                $class_name = apply_filters('digitalis_class_' . static::class, static::class);
                self::$instances[static::$post_type][$post_id] = new $class_name($post_id);

            } else {

                self::$instances[static::$post_type][$post_id] = null;

            }

        }

        return self::$instances[static::$post_type][$post_id];

    }

    public static function get_instances ($post_ids) {

        $instances = [];

        if ($post_ids) foreach ($post_ids as $post_id) {
            $instances[] = static::get_instance($post_id);
        }
        return $instances;

    }

    public static function get_query_args ($args = []) { // Override me.

        return wp_parse_args($args, [
            // ..
        ]);

    }

    public static function query ($args = [], &$query = null, $skip_main = false) {

        global $wp_query;

        $instances = [];
        $posts = [];

        if (!$skip_main && $wp_query && $wp_query->is_main_query() && ($wp_query->get('post_type') === static::$post_type)) {

            $query = $wp_query;
            $posts = $wp_query->posts;

        } else {

            $args = static::get_query_args($args);
            $args['post_type'] = static::$post_type;
            $query = new WP_Query($args);
            $posts = $query->get_posts();

        }

        return static::get_instances($posts);

    }

    //

    public function __construct($post_id) {

        $this->set_post($post_id);
        $this->init();

    }

    public function init () {}  // Override me.


}