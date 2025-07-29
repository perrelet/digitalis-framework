<?php

namespace Digitalis;

use stdClass;
use DateTime;
use DateTimeZone;
use WP_Comment;

trait Has_WP_Comment {

    use Has_WP_Model, Has_WP_Meta, Has_ACF_Fields;

    protected $wp_comment;

    protected function init_wp_model ($data) {

        if (is_int($data)) {

            $this->set_wp_comment(WP_Comment::get_instance($data));

        } else if ($data instanceof WP_Comment) {

            $this->set_wp_comment($data);

        } else {

            $this->set_wp_comment(new WP_Comment((object) $data));

        }

    }

    public function get_wp_comment () {

        return $this->wp_comment;

    }

    public function set_wp_comment ($wp_comment) {

        $this->wp_comment = $wp_comment;
        return $this;

    }

    // Traits

    public function get_wp_model () {

        return $this->wp_comment;

    }

    public function get_wp_model_id () {

        return $this->wp_comment->comment_ID;

    }

    public function get_wp_cache_group () {

        return 'comment';
    
    }

    public function get_wp_meta_type () {

        return 'comment';

    }

    public function get_acf_id () {

        return $this->is_new() ? null : 'comment_' . $this->wp_comment->comment_ID;

    }

    // Encapsulation

    public function get_post_id  () {

        return $this->wp_comment->comment_post_ID;

    }

    public function set_post_id ($comment_post_ID) {

        return $this->set_wp_model_prop('comment_post_ID', $comment_post_ID);

    }

    public function get_author () {

        return User::get_by_username($this->get_author_username());

    }

    public function get_author_username () {

        return $this->wp_comment->comment_author;

    }

    public function set_author ($author) {

        if ($author instanceof User)         { $author = $author->get_username(); }
        else if ($author instanceof WP_User) { $author = $author->user_login; }
        else if (!is_string($author))        { $author = (($user = User::inst($author)) ? $user->get_username() : null); }

        if ($author) return $this->set_wp_model_prop('comment_author', $author);

        return $this;

    }

    public function get_author_email () {

        return $this->wp_comment->comment_author_email;

    }

    public function set_author_email ($email) {

        return $this->set_wp_model_prop('comment_author_email', $email);

    }

    public function get_author_email_link ($link_text = '', $before = '', $after = '') {

        return get_comment_author_email_link($link_text, $before, $after, $this->wp_comment);

    }

    public function get_author_url () {

        return get_comment_author_url($this->wp_comment);

    }

    public function set_author_url ($url) {

        return $this->set_wp_model_prop('comment_author_url', $url);

    }

    public function get_author_link () {

        return get_comment_author_link($this->wp_comment);

    }

    public function get_comment_author_url_link ($link_text = '', $before = '', $after = '') {
    
        return get_comment_author_url_link($link_text, $before, $after, $this->wp_comment);
    
    }

    public function get_author_ip () {

        return get_comment_author_IP($this->wp_comment);

    }

    public function set_author_ip ($ip) {

        return $this->set_wp_model_prop('comment_author_IP', $ip);

    }

    public function get_date () {

        return $this->wp_comment->comment_date;

    }

    public function get_datetime () {
    
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->get_date());
    
    }

    public function set_date ($date) {

        if ($date instanceof DateTime) $date = $date->format('Y-m-d H:i:s');
        return $this->set_wp_model_prop('comment_date', $date);

    }

    public function get_date_gmt () {

        return $this->wp_comment->comment_date_gmt;

    }

    public function get_datetime_gmt () {
    
        return DateTime::createFromFormat('Y-m-d H:i:s', $this->get_date_gmt(), new DateTimeZone('GMT'));
    
    }

    public function set_date_gmt ($date) {

        if ($date instanceof DateTime) $date = $date->format('Y-m-d H:i:s');
        return $this->set_wp_model_prop('comment_date_gmt', $date);

    }

    public function get_comment_time ($format = '', $gmt = false, $translate = true) {

        return get_comment_time($format, $gmt, $translate, $this->wp_comment);

    }

    public function get_content ($args = []) {

        return get_comment_text($this->wp_comment, $args);

    }

    public function set_content ($content) {

        $this->wp_comment->comment_content = $content;
        return $this->set_wp_model_prop('comment_content', $content);

    }

    //

    public function get_comment_type () {

        return get_comment_type($this->wp_comment);

    }

    public function get_css_classes ($css_class = '', $post = null) {

        if ($post instanceof Post) $post = $post->get_wp_post();

        return get_comment_class($css_class, $this->wp_comment, $post);

    }

    public function get_excerpt () {

        return get_comment_excerpt($this->wp_comment);

    }

    public function get_url ($args = []) {

        return get_comment_link($this->wp_comment, $args);

    }

    public function get_reply_url ($args = [], $post = null) {

        if ($post instanceof Post) $post = $post->get_wp_post();

        return get_comment_reply_link($args , $this->wp_comment, $post);

    }

}