<?php

namespace Digitalis;

use \WP_User;
abstract class User {

    protected static $users = [];

    protected $id;
    protected $wp_user;

    public static function get_user ($user_id = null) {

        if (is_null($user_id)) $user_id = get_current_user_id();
        if (!isset(self::$users[$user_id])) self::$users[$user_id] = new User($user_id);

        return self::$users[$user_id];

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

    // https://developer.wordpress.org/reference/classes/wp_user/

    public function get_wp_user () {

        if (is_null($this->wp_user)) $this->wp_user = get_user_by('id', $this->id);

        return $this->wp_user;

    }

    public function set_wp_user ($wp_user) {

        if (!($wp_user instanceof WP_User)) return;

        $this->wp_user = $wp_user;

    }

    public function get_display_name () {

        return $this->get_wp_user()->display_name;

    }

}