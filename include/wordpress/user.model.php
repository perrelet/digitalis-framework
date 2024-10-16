<?php

namespace Digitalis;

use \WP_User;
use \DateTime;

class User extends Model {

    protected static $role = false; // string|false|array - Validate by user role. Leave false to allow any role.

    public static function extract_id ($data = null) {

        if (is_null($data))           return get_current_user_id();
        if ($data instanceof WP_User) return $data->ID;

        return (int) parent::extract_id($data);

    }

    public static function validate_id ($id) {

        //if ($id == 'new') return true;

        if (static::$role) {

            if (!is_array(static::$role))                         static::$role = [static::$role];
            if (!$wp_user = get_user_by('id', $id))               return false;
            if (!array_intersect(static::$role, $wp_user->roles)) return false;

        }

        return (is_int($id) && ($id > 0));

    }

    public static function get_user ($user_id = null) {

        return static::get_instance($user_id);

    }

    public static function get_by ($field, $value) {

        if (!$wp_user = get_user_by($field, $value)) return;
        if (!$user = static::get_instance($wp_user->ID)) return;

        $user->set_wp_user($wp_user);

        return $user;

    }

    public static function get_by_email ($email) {

        return static::get_by('email', $email);

    }

    public static function get_by_login ($login) {

        return static::get_by('login', $login);

    }

    public static function get_by_slug ($slug) {

        return static::get_by('slug', $slug);

    }

    //

    protected $wp_user;

    //

    public function get_role () {
    
        return ($roles = $this->get_roles()) ? $roles[0] : false;
    
    }

    public function get_roles () {

        return ($wp_user = $this->get_wp_user()) ? $wp_user->roles : [];

    }

    public function has_role ($role) {

        return ($roles = $this->get_roles()) ? (array_search($role, $roles) !== false) : false;

    }

    public function get_meta ($key, $single = true) {

       return get_user_meta($this->id, $key, $single);

    }

    public function update_meta ($key, $value, $prev_value = '') {

        return update_user_meta($this->id, $key, $value, $prev_value);
 
    }

    public function add_meta ($key, $value, $unique = false) {

        return add_user_meta($this->id, $key, $value, $unique);
 
    }

    public function get_field ($selector, $format_value = true) {

        return get_field($selector, "user_{$this->get_id()}", $format_value);

    }

    public function update_field ($selector, $value) {

        return update_field($selector, $value, "user_{$this->get_id()}");

    }

    public function get_wp_user () {

        if (is_null($this->wp_user)) $this->wp_user = get_user_by('id', $this->id);

        return $this->wp_user;

    }

    public function set_wp_user ($wp_user) {

        if (!($wp_user instanceof WP_User)) return;

        $this->wp_user = $wp_user;

    }

    public function get_username () {

        return $this->get_wp_user()->user_login;

    }

    public function get_email () {

        return $this->get_wp_user()->user_email;

    }

    public function get_display_name () {

        return $this->get_wp_user()->display_name;

    }

    public function get_nick_name () {

        return get_the_author_meta('nickname', $this->id);

    }

    public function get_nicename () {

        return $this->get_wp_user()->user_nicename;

    }

    public function get_nice_name () {

        return $this->get_nicename();

    }

    public function get_first_name () {

        return $this->get_wp_user()->user_firstname;

    }

    public function get_last_name () {

        return $this->get_wp_user()->user_lastname;

    }

    public function get_full_name () {

        return $this->get_first_name() . ($this->get_last_name() ? " " . $this->get_last_name() : "");

    }

    public function get_url () {

        return get_author_posts_url($this->id);

    }

    public function get_edit_url () {

        // https://core.trac.wordpress.org/ticket/35859

        $url = add_query_arg('user_id', $this->get_id(), self_admin_url('user-edit.php'));

        return apply_filters('get_edit_user_link', $url, $this->get_id());

    }

    public function get_edit_or_profile_url () {
    
        return get_edit_user_link($this->id);
    
    }

    public function get_avatar_url ($args = null) {

        return get_avatar_url($this->id, $args);

    }

    public function get_avatar ($size = 96, $default = '', $alt = '', $args = null) {

        return get_avatar($this->id, $size, $default, $alt, $args);

    }

    public function get_description () {
        
        return get_the_author_meta('description', $this->id);
        
    }

    public function get_registered_date () {
        
        return get_the_author_meta('user_registered', $this->id);
        
    }

    public function get_registered_datetime () {

        return DateTime::createFromFormat('Y-m-d H:i:s', $this->get_registered_date());

    }

    public function get_posts_count ($post_type = 'post', $public_only = false) {

        return count_user_posts($this->id, $post_type, $public_only);

    }

    //

    public function is_super_admin () {

        return is_super_admin($this->id);

    }

    public function can ($capability, ...$args) {

        return user_can($this->id, $capability, ...$args);

    }

    public function can_access_dashboard () {
    
        return $this->can('edit_posts');
    
    }

    public function get_admin_color () {
        
        return get_the_author_meta('admin_color', $this->id);
        
    }

    // CRUD

    public function save ($user_data = []) {

        $user_data['ID'] = $this->get_id();

        return wp_update_user($user_data);

        //TODO: wp_insert_user

    }

}