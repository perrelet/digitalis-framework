<?php

namespace Digitalis;

use \WP_User;
use \DateTime;

trait Has_WP_User {

    protected $wp_user;

    protected function init_wp_model ($data) {

        if (is_int($data)) {

            $this->set_wp_user(get_user_by('id', $data));

        } else if ($data instanceof WP_User) {

            $this->set_wp_user($data);

        } else {

            $this->set_wp_user(new WP_User($data));
            //if ($this->uid) wp_cache_set($this->uid, $this->get_wp_post(), 'posts');

        }

    }

    public function get_wp_user () {

        if (is_null($this->wp_user)) $this->wp_user = get_user_by('id', $this->id);

        return $this->wp_user;

    }

    public function set_wp_user ($wp_user) {

        if (!($wp_user instanceof WP_User)) return;

        $this->wp_user = $wp_user;

    }

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

    public function update_field ($selector, $value) {

        return update_field($selector, $value, "user_{$this->get_id()}");

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

    // Meta

    public function get_meta ($key, $single = true) {

        return get_user_meta($this->id, $key, $single);

    }

    public function add_meta ($key, $value, $unique = false) {

        return add_user_meta($this->id, $key, $value, $unique);

    }

    public function update_meta ($key, $value, $prev_value = '') {

        return update_user_meta($this->id, $key, $value, $prev_value);

    }

    public function get_field ($selector, $format_value = true) {

        return get_field($selector, "user_{$this->get_id()}", $format_value);

    }

}