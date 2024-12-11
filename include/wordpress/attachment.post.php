<?php

namespace Digitalis;

class Attachment extends Post {

    protected static $post_type = 'attachment';

    public function is ($type) {
    
        return wp_attachment_is($type, $this->wp_post);
    
    }

    public function is_image () {
    
        return wp_attachment_is_image($this->wp_post);
    
    }

    public function get_path ($unfiltered = false) {
    
        return get_attached_file($this->wp_post->ID, $unfiltered);
    
    }

    public function get_file_name ($unfiltered = false) {
    
        return basename($this->get_path($unfiltered));
    
    }

    public function get_file_extension ($unfiltered = false) {
    
        return pathinfo($this->get_file_name($unfiltered), PATHINFO_EXTENSION);
    
    }

    public function get_permalink ($leavename = false) {
    
        return get_attachment_link($this->wp_post, $leavename);
    
    }

    public function get_file_url ($not_used = false) {
    
        return wp_get_attachment_url($this->wp_post->ID);
    
    }

    public function get_mime_type () {
    
        return get_post_mime_type($this->wp_post);
    
    }

    public function set_mime_type ($mime_type) {
    
        $this->wp_post->post_mime_type = $mime_type;
        return $this;
    
    }

    public function get_caption () {
    
        return wp_get_attachment_caption($this->wp_post->ID);
    
    }

    public function get_attachment_thumbnail () {
    
        return wp_get_attachment_thumb_url($this->wp_post->ID);
    
    }

    public function has_image () {
        
        return (bool) $this->get_image();
        
    }

    public function get_image ($size = 'thumbnail', $attr = '', $icon = false) {

        return wp_get_attachment_image($this->wp_post->ID, $size, $icon, $attr);

    }

    public function get_image_url ($size = 'thumbnail', $icon = false) {

        return wp_get_attachment_image_url($this->wp_post->ID, $size, $icon);

    }

    public function get_image_src ($size = 'medium', $icon = false) {

        static $src;
        if (is_null($src)) $src = [];

        $key = implode(func_get_args(), ';');

        if (!isset($src[$key])) $src[$key] = wp_get_attachment_image_src($this->wp_post->ID, $size, $icon);
    
        return $src[$key];
    
    }

    public function get_image_width ($size = 'medium', $icon = false) {
    
        return ($src = $this->get_image_src($size, $icon)) ? $src[1] : null;
    
    }

    public function get_image_height ($size = 'medium', $icon = false) {
    
        return ($src = $this->get_image_src($size, $icon)) ? $src[2] : null;
    
    }

    public function get_image_is_resized ($size = 'medium', $icon = false) {
    
        return ($src = $this->get_image_src($size, $icon)) ? $src[3] : null;
    
    }

    public function get_image_srcset ($size = 'medium', $image_meta = null) {
    
        return wp_get_attachment_image_srcset($this->wp_post->ID, $size, $image_meta);
    
    }

    public function get_image_sizes ($size = 'medium', $image_meta = null) {
    
        return wp_get_attachment_image_sizes($this->wp_post->ID, $size, $image_meta);
    
    }

    public function get_id3_keys ($context = 'display') {
    
        return wp_get_attachment_id3_keys($this->wp_post, $context);
    
    }

    public function get_metadata ($unfiltered = false) {
    
        return wp_get_attachment_metadata($this->wp_post->ID, $unfiltered);
    
    }

    public function update_metadata ($data) {
    
        return wp_update_attachment_metadata($this->wp_post->ID, $data);
    
    }

}