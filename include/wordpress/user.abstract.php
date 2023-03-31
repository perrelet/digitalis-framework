<?php

namespace Digitalis;

use \WP_User;
abstract class User {

    protected static $users = [];

    protected $id;
    protected $wp_user;

    public static function get_user ($user_id = null) {

        if (is_null($user_id)) $user_id = get_current_user_id();
        if (!isset(static::$users[$user_id])) {

            $class_name = static::class;
            static::$users[$user_id] = new $class_name($user_id);

        }

        return static::$users[$user_id];

    }

    public static function get_by ($field, $value) {

        if (!$wp_user = get_user_by($field, $value)) return;
        if (!$user = static::get_user($wp_user->ID)) return;

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

    public function __construct ($user_id = null) {

        if (is_null($user_id)) $user_id = get_current_user_id();

        $this->id = $user_id;

        $this->init();

    }

    public function init () {}

    public function get_id () {

        return $this->id;

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

    // https://developer.wordpress.org/reference/classes/wp_user/

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

        return get_edit_user_link($this->id);

    }

    public function get_avatar_url ($args = null) {

        return get_avatar_url($this->id, $args);

    }

    public function get_avatar ($size = 96, $default = '', $alt = '', $args = null) {

        return get_avatar($this->id, $size, $default, $alt, $args);

    }

}