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

    public function get_url ($not_used = false) {
    
        return wp_get_attachment_url($this->wp_post->ID);
    
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