<?php

namespace Digitalis;

use stdClass;
use DateTime;
use WP_User;
use WP_User_Query;

class User extends WP_Model {

    use Has_WP_User;

    protected static $role = false; // string|false|array - Validate by user role. Leave false to allow any role.

    public static function get_global_id () {
    
        return ($id = get_current_user_id()) ? $id : null;
    
    }

    public static function extract_id ($data = null) {

        if (is_object($data) && property_exists($data, 'ID'))   return $data->ID;
        if (is_object($data) && method_exists($data, 'get_id')) return $data->get_id();

        return (int) parent::extract_id($data);

    }

    public static function validate_id ($id) {

        //if ($id == 'new') return true;

        if (static::$role) {

            if (!is_array(static::$role))                         static::$role = [static::$role];
            if (!$wp_user = get_user_by('id', $id))               return false;
            if (!array_intersect(static::$role, $wp_user->roles)) return false;

        }

        return parent::validate_id($id);

    }

    public static function get_specificity () {
    
        return (int) ((bool) static::$role);
    
    }

    public static function get_by ($field, $value) {

        if (!$wp_user = get_user_by($field, $value)) return;
        if (!$user = static::get_instance($wp_user->ID)) return;

        return $user;

    }

    public static function get_by_email ($email) {

        return static::get_by('email', $email);

    }

    public static function get_by_login ($login) {

        return static::get_by('login', $login);

    }

    public static function get_by_username ($username) {

        return static::get_by_login($username);

    }

    public static function get_by_slug ($slug) {

        return static::get_by('slug', $slug);

    }

    //

    public static function query ($args = [], &$query = null) {

        $args = (is_admin() && !wp_doing_ajax()) ? static::get_admin_query_vars($args) : static::get_query_vars($args);
    
        if (static::$role) $args['role__in'] = (array) static::$role;

        $query    = new WP_User_Query($args);
        $wp_users = $query->get_results(); // lol, results?

        $instances = static::get_instances($wp_users);

        return $instances;
    
    }

    public static function get_query_vars ($args = []) {

        return $args;

    }

    public static function get_admin_query_vars ($args = []) {

        return $args;

    }

    //

    protected function build_instance ($data) {

        $wp_user     = new WP_User((object) $data);
        $wp_user->ID = $this->id;

        if (static::$role) $wp_user->roles = static::$role;

        parent::build_instance($wp_user);

    }

    // CRUD

    public function save ($user_data = []) {

        $user_data['ID'] = $this->get_id();

        return wp_update_user($user_data);

        //TODO: wp_insert_user

    }

}