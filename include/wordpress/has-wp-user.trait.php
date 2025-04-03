<?php

namespace Digitalis;

use DateTime;
use WP_Error;
use WP_User;

trait Has_WP_User {

    use Has_WP_Model, Has_WP_Meta, Has_ACF_Fields;

    protected $wp_user;

    protected function init_wp_model ($data) {

        if (is_int($data)) {

            $this->set_wp_user(get_user_by('id', $data));

        } else if ($data instanceof WP_User) {

            $this->set_wp_user($data);

        } else {

            $this->set_wp_user(new WP_User($data));

        }

    }

    public function get_wp_user () {

        if (is_null($this->wp_user)) $this->wp_user = get_user_by('id', $this->wp_user->ID);

        return $this->wp_user;

    }

    public function set_wp_user ($wp_user) {

        if (!($wp_user instanceof WP_User)) return;

        $this->wp_user = $wp_user;

    }

    // Traits

    public function get_wp_model () {

        return $this->wp_user;

    }

    public function get_wp_model_id () {

        return $this->wp_user->ID;

    }

    public function get_wp_cache_group () {

        return 'users';
    
    }

    public function get_wp_meta_type () {

        return 'user';

    }

    public function get_acf_id () {

        return $this->is_new() ? null : 'user_' . $this->wp_user->ID;

    }

    // Encapsulation

    public function get_role () {
    
        return ($roles = $this->get_roles()) ? $roles[0] : false;
    
    }

    public function get_roles () {

        return ($wp_user = $this->wp_user) ? $wp_user->roles : [];

    }

    public function has_role ($role) {

        return ($roles = $this->get_roles()) ? (array_search($role, $roles) !== false) : false;

    }

    public function set_roles ($roles) {

        $this->wp_user->roles = (array) $roles;
        $this->cache_wp_model();
        return $this;

    }

    public function add_role ($role) {
    
        $this->wp_user->roles[] = $role;
        $this->cache_wp_model();
        return $this;
    
    }

    public function get_username () {

        return $this->wp_user->user_login;

    }

    public function set_username ($username) {

        $this->wp_user->user_login = $username;
        $this->cache_wp_model();
        return $this;

    }

    public function get_password () {

        return $this->wp_user->user_pass;

    }

    public function set_password ($password) {

        $this->wp_user->user_pass           = wp_hash_password($password);
        $this->wp_user->user_activation_key = '';
        $this->cache_wp_model();
        return $this;

    }

    public function is_password_reset_allowed () {

        return wp_is_password_reset_allowed_for_user($this->wp_user);

    }

    public function get_password_reset_key () {

        return $this->is_new() ? null : get_password_reset_key($this->wp_user);

    }

    public function get_email () {

        return $this->wp_user->user_email;

    }

    public function set_email ($email) {

        $this->wp_user->user_email = $email;
        $this->cache_wp_model();
        return $this;

    }

    public function get_user_url () {

        return $this->wp_user->user_url;

    }

    public function set_user_url ($url) {

        $this->wp_user->user_url = $url;
        $this->cache_wp_model();
        return $this;

    }

    public function get_registered_date () {

        return $this->wp_user->user_registered;

    }

    public function get_registered_datetime () {

        return DateTime::createFromFormat('Y-m-d H:i:s', $this->get_registered_date());

    }

    public function set_registered_date ($date) {

        if ($date instanceof DateTime) $date = $date->format('Y-m-d H:i:s');

        $this->wp_user->user_registered = $date;
        $this->cache_wp_model();
        return $this;

    }

    // Names

    public function get_display_name () {

        return $this->wp_user->display_name;

    }

    public function set_display_name ($display_name) {

        $this->wp_user->display_name = $display_name;
        $this->cache_wp_model();
        return $this;

    }

    public function get_nicename () {

        return $this->wp_user->user_nicename;

    }

    public function get_nice_name () {

        return $this->get_nicename();

    }

    public function set_nicename ($nicename) {

        $this->wp_user->user_nicename = $nicename;
        $this->cache_wp_model();
        return $this;

    }

    public function set_nice_name ($nice_name) {

        return $this->set_nicename($nice_name);

    }

    public function get_nick_name () {

        return get_the_author_meta('nickname', $this->wp_user->ID);

    }

    public function get_first_name () {

        return $this->wp_user->user_firstname;

    }

    public function get_last_name () {

        return $this->wp_user->user_lastname;

    }

    public function get_full_name () {

        return $this->get_first_name() . ($this->get_last_name() ? " " . $this->get_last_name() : "");

    }

    // Capabilities

    public function is_super_admin () {

        return is_super_admin($this->wp_user->ID);

    }

    public function can ($capability, ...$args) {

        return user_can($this->wp_user->ID, $capability, ...$args);

    }

    public function can_access_dashboard () {
    
        return $this->can('edit_posts');
    
    }

    //

    public function is_spammer () {

        return is_user_spammy($this->wp_user);

    }

    public function get_sites ($all = false) {

        return get_blogs_of_user($this->wp_user->ID, $all);

    }

    public function is_site_member ($blog_id = 0) {

        return is_user_member_of_blog($this->wp_user->ID, $blog_id);

    }

    public function get_url () {

        return get_author_posts_url($this->wp_user->ID);

    }

    public function get_edit_url () {

        // https://core.trac.wordpress.org/ticket/35859

        $url = add_query_arg('user_id', $this->wp_user->ID, self_admin_url('user-edit.php'));

        return apply_filters('get_edit_user_link', $url, $this->wp_user->ID);

    }

    public function get_edit_or_profile_url () {

        return get_edit_user_link($this->wp_user->ID);

    }

    public function get_avatar_url ($args = null) {

        return get_avatar_url($this->wp_user->ID, $args);

    }

    public function get_avatar ($size = 96, $default = '', $alt = '', $args = null) {

        return get_avatar($this->wp_user->ID, $size, $default, $alt, $args);

    }

    public function get_description () {

        return get_the_author_meta('description', $this->wp_user->ID);

    }

    public function get_posts_count ($post_type = 'post', $public_only = false) {

        return count_user_posts($this->wp_user->ID, $post_type, $public_only);

    }

    public function get_admin_color () {

        return get_the_author_meta('admin_color', $this->wp_user->ID);

    }

    //

    public function send_new_user_notifications ($notify = 'both') {
    
        return wp_send_new_user_notifications($this->wp_user->ID, null, $notify);
    
    }

    //

    public function get_option ($option) {

        return get_user_option($option, $this->wp_user->ID);

    }

    public function update_option ($option, $value, $is_global = false) {

        return update_user_option($this->wp_user->ID, $option, $value, $is_global);

    }

    public function delete_option ($option, $is_global = false) {

        return delete_user_option($this->wp_user->ID, $option, $is_global);

    }

}