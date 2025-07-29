<?php

namespace Digitalis;

use stdClass;
use WP_Post;

trait Has_WP_Post {

    use Has_WP_Model, Has_WP_Meta, Has_ACF_Fields;

    protected $wp_post;

    protected function init_wp_model ($data) {

        if (is_int($data)) {

            $this->set_wp_post(WP_Post::get_instance($data));

        } else if ($data instanceof WP_Post) {

            $this->set_wp_post($data);

        } else {

            $this->set_wp_post(new WP_Post((object) $data));

        }

    }

    public function get_wp_post () {

        return $this->wp_post;

    }

    public function set_wp_post ($wp_post) {

        $wp_post->ID = $this->get_id();
        $this->wp_post = $wp_post;
        return $this;

    }

    // Traits

    public function get_wp_model () {

        return $this->wp_post;

    }

    public function get_wp_model_id () {

        return $this->wp_post->ID;

    }

    public function get_wp_cache_group () {

        return 'posts';
    
    }

    public function get_wp_meta_type () {

        return 'post';

    }

    public function get_acf_id () {

        return $this->is_new() ? null : $this->wp_post->ID;

    }

    // Encapsulation

    public function get_slug () {
        
        return $this->wp_post->post_name;
        
    }

    public function set_slug ($slug) {

        return $this->set_wp_model_prop('post_name', $slug);

    }

    public function get_guid () {

        return get_the_guid($this->wp_post);

    }

    public function set_guid ($guid) {

        return $this->set_wp_model_prop('guid', $guid);

    }

    public function get_css_classes ($class = '') {

        return get_post_class($class, $this->wp_post);

    }

    public function get_title () {

        return get_the_title($this->wp_post);

    }

    public function set_title ($title) {

        return $this->set_wp_model_prop('post_title', $title);

    }

    protected $content_cache = [];

    public function get_content ($apply_filters = true, $more_link_text = null, $strip_teaser = false) {

        $key = serialize(array_values(func_get_args()));

        if (!isset($this->content_cache[$key])) {

            $content = get_the_content($more_link_text, $strip_teaser, $this->wp_post);
            if ($apply_filters) $content = apply_filters('the_content', $content);
            $this->content_cache[$key] = $content;

        }

        return $this->content_cache[$key];

    }

    public function set_content ($content) {
    
        $this->clear_content_cache();
        return $this->set_wp_model_prop('post_content', $content);
    
    }

    public function clear_content_cache () {
    
        $this->content_cache = [];
    
    }

    public function get_excerpt ($force_trim = false) {

        $excerpt = get_the_excerpt($this->wp_post);

        if ($force_trim) $excerpt = wp_trim_words($excerpt, apply_filters('excerpt_length', (int) _x('55', 'excerpt_length')), apply_filters('excerpt_more', ' ' . '[&hellip;]'));

        return $excerpt;

    }

    public function set_excerpt ($excerpt) {
    
        return $this->set_wp_model_prop('post_excerpt', $excerpt);
    
    }

    public function get_status () {

        return get_post_status($this->wp_post);

    }

    public function set_status ($status) {
    
        return $this->set_wp_model_prop('post_status', $status);
    
    }

    public function is_auto_draft () {

        return $this->get_status() == 'auto-draft';

    }

    public function is_sticky () {

        return is_sticky($this->wp_post->ID);

    }

    // Type

    public function get_type () { // REFACTOR get_post_type()

        return $this->wp_post->post_type;

    }

    public function set_post_type ($post_type) {
    
        return $this->set_wp_model_prop('post_type', $post_type);
    
    }

    public function get_post_type_object () {

        return get_post_type_object($this->get_type());

    }

    // URLs

    public function get_permalink ($leavename = false) {

        return get_permalink($this->wp_post, $leavename);

    }

    public function get_url ($leavename = false) {

        return $this->get_permalink($leavename);

    }

    public function get_edit_url ($context = false) {

        return get_edit_post_link($this->wp_post, $context);

    }

    public function get_archive_url () {
    
        return get_post_type_archive_link($this->get_type());
    
    }

    // Dates

    public function get_date ($format = '') {

        return get_the_date($format, $this->wp_post);

    }

    public function get_date_modified ($format = '') {

        return get_the_modified_date($format, $this->wp_post);

    }

    public function get_post_datetime ($field = 'date', $source = 'local') {

        return get_post_datetime($this->wp_post, $field, $source);

    }

    public function get_post_time ($format = 'U', $gmt = false, $translate = false) {

        return get_post_time($format, $gmt, $this->wp_post, $translate);

    }

    public function get_time_modified ($format = 'U', $gmt = false, $translate = false) {

        return get_post_modified_time($format, $gmt, $this->wp_post, $translate);

    }

    public function get_post_timestamp ($field = 'date') {

        return get_post_timestamp($this->wp_post, $field);

    }

    public function set_date ($date) {
    
        return $this->set_wp_model_prop('post_date', $date);
    
    }

    public function set_date_gmt ($date) {
    
        return $this->set_wp_model_prop('post_date_gmt', $date);
    
    }

    // Author

    public function get_author_id () {

        return (int) $this->wp_post->post_author;

    }

    public function set_author_id ($author_id) {
    
        return $this->set_wp_model_prop('post_author', $author_id);

    }

    public function get_author ($auto_resolve = true) {

        return User::get_instance($this->get_author_id(), $auto_resolve);

    }

    public function set_author ($user) {
    
        if (($user instanceof User) && ($user_id = $user->get_id())) $this->set_author_id($user_id);
        return $this;
    
    }

    // Attachments

    public function get_attached_media ($post_mime_type = '') {

        return get_attached_media($post_mime_type, $this->wp_post->ID);

    }

    public function get_attachments ($post_mime_type = '') {
    
        $models = [];

        if ($attachments = $this->get_attached_media($post_mime_type)) foreach ($attachments as $attachment) {
        
            if ($model = Attachment::get_instance($attachment)) $models[] = $model;
        
        }

        return $models;
    
    }

    public function has_thumbnail () {
        
        return has_post_thumbnail($this->wp_post->ID);
        
    }

    public function has_image () {
        
        return $this->has_thumbnail();
        
    }

    public function get_thumbnail ($size = 'post-thumbnail', $attr = '') {

        return get_the_post_thumbnail($this->wp_post->ID, $size, $attr);

    }

    public function get_image ($size = 'post-thumbnail', $attr = '') {

        return $this->get_thumbnail($size, $attr);

    }

    public function get_thumbnail_url ($size = 'post-thumbnail') {

        return get_the_post_thumbnail_url($this->wp_post->ID, $size);

    }

    public function get_image_url ($size = 'post-thumbnail') {

        return $this->get_thumbnail_url($size);

    }

    public function get_thumbnail_id () {
        
        return get_post_thumbnail_id($this->wp_post->ID);
        
    }

    public function get_image_id () {
        
        return $this->get_thumbnail_id();
        
    }

    // Terms

    public function get_terms ($tax_or_term) {

        if (is_subclass_of($tax_or_term, Term::class)) {

            return Call::static($tax_or_term, 'query_post', $this);

        } else {

            return get_the_terms($this->wp_post->ID, $tax_or_term);

        }

    }

    public function has_term ($term = '', $taxonomy = '') {
        
        return has_term($term, $taxonomy, $this->wp_post->ID);
        
    }

    public function set_terms ($terms, $taxonomy = 'post_tag', $append = false) {
    
        return wp_set_post_terms($this->wp_post->ID, $terms, $taxonomy, $append);
    
    }

    public function add_terms ($terms, $taxonomy = 'post_tag') {
    
        return $this->set_terms($terms, $taxonomy, true);
    
    }

    public function remove_terms ($terms, $taxonomy = 'post_tag') {
    
        return wp_remove_object_terms($this->wp_post->ID, $terms, $taxonomy);
    
    }

    // Post Parent

    public function has_post_parent () {

        return has_post_parent($this->wp_post);

    }

    public function get_post_parent () {

        return get_post_parent($this->wp_post);

    }

    public function get_parent_id () {

        return $this->wp_post->post_parent;

    }

    public function set_parent_id ($parent_id) {

        return $this->set_wp_model_prop('post_parent', (int) $parent_id);

    }

    public function get_parent () {

        return ($parent_id = $this->get_parent_id()) ? static::get_instance($parent_id) : null;

    }

    public function set_parent ($post) {

        if (($post instanceof Post) && ($post_id = $post->get_id())) $this->set_parent_id($post_id);
        return $this;

    }

    // Revisions

    public function is_revisions_enabled () {

        return wp_revisions_enabled($this->wp_post);

    }

    public function get_max_revisions () {
    
        return wp_revisions_to_keep($this->wp_post);
    
    }

    public function get_revisions () {

        return Revision::get_instances(wp_get_post_revisions($this->wp_post, $args = null));

    }

    public function get_latest_revision_id_and_total_count () {

        return wp_get_latest_revision_id_and_total_count($this->wp_post);

    }

    public function get_latest_revision_id () {

        $info = $this->get_latest_revision_id_and_total_count();
        return is_array($info) ? ($info['latest_id'] ?? null) : null;

    }

    public function get_latest_revision () {

        return ($id = $this->get_latest_revision_id()) ? Revision::get_instance($id) : null;

    }

    public function get_revision_count () {

        $info = $this->get_latest_revision_id_and_total_count();
        return is_array($info) ? ($info['count'] ?? null) : null;

    }

    public function get_revisions_url () {

        return wp_get_post_revisions_url($this->wp_post);

    }

    public function get_autosave ($user_id = 0) {

        return wp_get_post_autosave($this->wp_post->ID, $user_id);

    }

    // Comments

    public function get_comments_url () {

        return get_comments_link($this->wp_post);

    }

    public function get_comment_count () {

        return get_comments_number($this->wp_post);

    }

    public function get_comment_count_text ($zero = false, $one = false, $more = false) {

        return get_comment_count($zero, $one, $more, $this->wp_post);

    }

    public function is_comments_open () {

        return comments_open($this->wp_post);

    }

    public function is_pings_open () {

        return pings_open($this->wp_post);

    }

    public function get_reply_url ($args = []) {

        return get_post_reply_link($args, $this->wp_post);

    }

    public function get_cancel_reply_url ($link_text = '') {

        return get_cancel_comment_reply_link($link_text, $this->wp_post);

    }

    public function get_comment_id_fields () {

        return get_comment_id_fields($this->wp_post);

    }

    public function get_comment_form_title ($no_reply_text = false, $reply_text = false, $link_to_parent = true) {

        return comment_form_title($no_reply_text, $reply_text, $link_to_parent, $this->wp_post);

    }

    public function get_comment_form ($args = []) {

        ob_start();
        comment_form($args, $this->wp_post);
        $html = ob_get_contents();
        ob_end_clean();
        return $html;

    }

    // Password Protection

    public function is_password_protected () {

        return post_password_required($this->wp_post);

    }

    public function get_password_form () {

        return get_the_password_form($this->wp_post);

    }

    public function set_password ($password) {

        return $this->set_wp_model_prop('post_password', $password);

    }

    // Data Access

    /* public function reload_wp_post () {

        $this->init_wp_model($this->id);
        $this->content_cache = [];
    
    } */


}