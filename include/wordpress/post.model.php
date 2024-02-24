<?php

namespace Digitalis;

use stdClass;
use WP_Post;
use WP_Query;

class Post extends Model {

    use Has_WP_Post;

    protected static $post_type       = false;      // Override me. Leave false to allow any generic post type.
    protected static $post_type_class = false;      // Override me - Used when querying the model. With this we can get the main query args from the CPT.

    public function is_post () { return true; }

    public static function process_data (&$data) {

        if (is_array($data)) $data = (object) $data;

    }

    public static function extract_id ($data = null) {

        global $post;

        if (is_null($data) && $post)   return $post->ID;
        if ($data instanceof WP_Post)  return $data->ID;
        if ($data instanceof stdClass) return 'new';
        if ($data == 'new')            return 'new';

        return (int) parent::extract_id($data);

    }

    public static function extract_uid ($id, $data = null) {

        if ($id == 'new') return random_int(1000000000, PHP_INT_MAX);

        return parent::extract_uid($id, $data);

    }

    public static function validate_id ($id) {

        if ($id == 'new')                                                     return true;
        if (static::$post_type && (get_post_type($id) != static::$post_type)) return false;

        return (is_int($id) && ($id > 0));

    }

    //

    public static function get_query_var ($key = 'posts_per_page', $default = '', $args = [], $query_key = null, $query = null) {

        if (isset($args[$key])) return $args[$key];

        if (!($query instanceof WP_Query)) {

            global $wp_query;
            $query = $wp_query;

        }

        return $query->get($query_key ? $query_key : $key, $default);

    }

    public static function get_query_vars ($args = []) {

        $call = static::$post_type_class . "::get_query_vars";
        return is_callable($call) ? call_user_func($call) : [];

    }

    public static function get_admin_query_vars ($args = []) {

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
            $query->merge((is_admin() && !wp_doing_ajax()) ? static::get_admin_query_vars($args) : static::get_query_vars($args), true);
            $query->merge($args, true);

            $posts = $query->query();

        }

        return static::get_instances($posts);

    }

    protected static function query_is_post_type ($wp_query) {

        return Digitalis_Query::compare_post_type($wp_query, static::$post_type);

    }

    //

    public function __construct ($data = null, $uid = null, $id = null) {

        parent::__construct($data, $uid, $id);

        if ($this->id == 'new') {

            if (!is_object($this->data)) $this->data = new stdClass();

            $this->data->ID           = $this->uid;
            $this->data->post_type    = static::$post_type;
            if (!property_exists($this->data, 'post_content')) $this->data->post_content = '';

            $this->set_post('new', $this->data);

        } else {

            $this->set_post($this->id);

        }

    }

    public function get_global_var () {

        return "_" . static::$post_type;

    }

}