<?php

namespace Lattice;

class Attachment extends Post {

    protected static $post_type = 'attachment';

    public function is (string $type) {
    
        return wp_attachment_is($type, $this->wp_post);
    
    }

    public function is_image () {
    
        return wp_attachment_is_image($this->wp_post);
    
    }

    public function get_path (bool $unfiltered = false) {
    
        return get_attached_file($this->wp_post->ID, $unfiltered);
    
    }

    public function get_file_name (bool $unfiltered = false) {
    
        return basename($this->get_path($unfiltered));
    
    }

    public function get_file_extension (bool $unfiltered = false) {
    
        return pathinfo($this->get_file_name($unfiltered), PATHINFO_EXTENSION);
    
    }

    public function get_permalink (bool $leavename = false) {
    
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

    public function get_alt_text () {

        return $this->get_meta('_wp_attachment_image_alt');

    }

    public function set_alt_text ($alt_text) {

        $this->update_meta('_wp_attachment_image_alt', wp_slash($alt_text));
        return $this;

    }

    public function get_attachment_thumbnail () {
    
        return wp_get_attachment_thumb_url($this->wp_post->ID);
    
    }

    public function has_image () {
        
        return (bool) $this->get_image();
        
    }

    public function get_image (string|array $size = 'thumbnail', string|array $attr = '', bool $icon = false) {

        return wp_get_attachment_image($this->wp_post->ID, $size, $icon, $attr);

    }

    public function get_image_url (string|array $size = 'thumbnail', bool $icon = false) {

        return wp_get_attachment_image_url($this->wp_post->ID, $size, $icon);

    }

    protected $src_cache = [];

    public function get_image_src (string|array $size = 'medium', bool $icon = false) {

        $key = implode(';', func_get_args());
        if (!isset($this->src_cache[$key])) $this->src_cache[$key] = wp_get_attachment_image_src($this->wp_post->ID, $size, $icon);
        return $this->src_cache[$key];
    
    }

    public function get_image_width (string|array $size = 'medium', bool $icon = false) {
    
        return ($src = $this->get_image_src($size, $icon)) ? $src[1] : null;
    
    }

    public function get_image_height (string|array $size = 'medium', bool $icon = false) {
    
        return ($src = $this->get_image_src($size, $icon)) ? $src[2] : null;
    
    }

    public function get_focal_point () {

        $x = $this->get_field('focal_x');
        $y = $this->get_field('focal_y');

        if (!is_numeric($x) && !is_numeric($y)) return null;

        return [is_numeric($x) ? (float) $x : 50.0, is_numeric($y) ? (float) $y : 50.0];

    }

    // `full`, unlike its neighbours: a hard-cropped size reports its own ratio, not the image's
    public function get_aspect_ratio (string|array $size = 'full', bool $icon = false) {

        $w = $this->get_image_width($size, $icon);
        $h = $this->get_image_height($size, $icon);

        return ($w && $h) ? ($w / $h) : null;

    }

    public function get_image_is_resized (string|array $size = 'medium', bool $icon = false) {
    
        return ($src = $this->get_image_src($size, $icon)) ? $src[3] : null;
    
    }

    public function get_image_srcset (string|array $size = 'medium', ?array $image_meta = null) {
    
        return wp_get_attachment_image_srcset($this->wp_post->ID, $size, $image_meta);
    
    }

    public function get_image_sizes (string|array $size = 'medium', ?array $image_meta = null) {
    
        return wp_get_attachment_image_sizes($this->wp_post->ID, $size, $image_meta);
    
    }

    public function get_id3_keys (string $context = 'display') {
    
        return wp_get_attachment_id3_keys($this->wp_post, $context);
    
    }

    public function get_attachment_taxonomies (string $output = 'names') {
    
        return get_taxonomies($this->wp_post, $output);
    
    }

    public function get_metadata (bool $unfiltered = false) {
    
        return wp_get_attachment_metadata($this->wp_post->ID, $unfiltered);
    
    }

    public function update_metadata (array $data) {
    
        return wp_update_attachment_metadata($this->wp_post->ID, $data);
    
    }

}