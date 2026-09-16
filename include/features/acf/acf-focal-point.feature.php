<?php

namespace Digitalis\ACF;

use Digitalis\Attachment;
use Digitalis\Feature;

class Focal_Point extends Feature {

    protected $focal = null;

    protected $group_key    = 'group_focal_point';
    protected $title        = 'Framing';
    protected $location     = [[['param' => 'attachment', 'operator' => '==', 'value' => 'all']]];
    protected $instructions = 'Where the subject sits, as a percentage across and down the image. Leave blank to centre.';

    public function get_hooks () {

        return [
            'acf/include_fields'                 => 'register_fields',
            'acf/update_value/key=field_focal_x' => 'clamp_value',
            'acf/update_value/key=field_focal_y' => 'clamp_value',
            'wp_get_attachment_image_attributes' => 'add_focal_style',
            'acf/save_post'                      => ['recrop_sizes', 20],
            'image_resize_dimensions'            => 'resize_dimensions',
        ];

    }

    // FIELDS

    public function register_fields () {

        acf_add_local_field_group([
            'key'                   => $this->group_key,
            'title'                 => $this->title,
            'location'              => $this->location,
            'menu_order'            => 0,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
            'active'                => true,
            'show_in_rest'          => 0,
            'description'           => $this->instructions,
            'fields'                => [
                $this->build_field('x', 'Focal X', 'across from the left'),
                $this->build_field('y', 'Focal Y', 'down from the top'),
            ],
        ]);

    }

    protected function build_field ($axis, $label, $hint) {

        return [
            'key'          => "field_focal_{$axis}",
            'label'        => $label,
            'name'         => "focal_{$axis}",
            'type'         => 'number',   // not `range`: a range input always posts a value, so it cannot express unset
            'instructions' => "0-100, {$hint}. Blank centres.",
            'min'          => 0,
            'max'          => 100,
            'step'         => 0.1,
            'placeholder'  => 50,
            'append'       => '%',
            'wrapper'      => ['width' => '50'],
        ];

    }

    // min/max go unvalidated on the media modal's ajax save (form-attachment.php:194)
    public function clamp_value ($value) {

        return is_numeric($value) ? max(0, min(100, (float) $value)) : $value;

    }

    // RENDER

    public function add_focal_style ($attr, $attachment, $size) {

        if (!$style = $this->get_focal_style(Attachment::get_instance($attachment->ID), $size)) return $attr;

        $attr['style'] = trim(($attr['style'] ?? '') . ' ' . $style);

        return $attr;

    }

    public function get_focal_style ($attachment, $size = 'full') {

        if (!$attachment instanceof Attachment)   return '';
        if (!is_string($size))                    return '';
        if (!$focal = $attachment->get_focal_point()) return '';

        [$x, $y] = $this->resolve_axes($attachment, $size, $focal);

        return trim(
            (is_null($x) ? '' : sprintf('--focal-x: %s%%;', round($x, 2))) . ' ' .
            (is_null($y) ? '' : sprintf('--focal-y: %s%%;', round($y, 2)))
        );

    }

    // a hard-cropped derivative is already centred on the focal point, so it carries the residual instead
    protected function resolve_axes ($attachment, $size, $focal) {

        $sizes = wp_get_registered_image_subsizes();

        if (empty($sizes[$size]['crop'])) return $focal;

        $w = $attachment->get_image_width('full');
        $h = $attachment->get_image_height('full');

        if (!$w || !$h) return [null, null];

        [$crop_w, $crop_h] = static::calculate_crop($w, $h, $sizes[$size]['width'], $sizes[$size]['height']);

        return [
            static::calculate_residual($focal[0], $w, $crop_w),
            static::calculate_residual($focal[1], $h, $crop_h),
        ];

    }

    // CROP

    public function recrop_sizes ($post_id) {

        if (!is_numeric($post_id) || get_post_type($post_id) != 'attachment') return;

        $attachment = Attachment::get_instance($post_id);
        $focal      = $attachment->get_focal_point();
        $applied    = $focal ? implode(',', $focal) : '';

        if ($attachment->get_meta('_focal_applied') === $applied) return;

        // Subsize filenames derive from this basename, so a `-scaled` source writes files the metadata misses.
        $file = wp_get_original_image_path($post_id) ?: get_attached_file($post_id);
        $meta = wp_get_attachment_metadata($post_id);

        if (!$file || !file_exists($file) || empty($meta['sizes'])) return;

        $this->focal = $focal;

        try {

            foreach ($this->get_cropped_sizes() as $size => $dims) {

                if (!isset($meta['sizes'][$size])) continue;

                if ($resized = image_make_intermediate_size($file, $dims['width'], $dims['height'], $dims['crop'])) {
                    $meta['sizes'][$size] = $resized;
                }

            }

        } finally {

            $this->focal = null;

        }

        wp_update_attachment_metadata($post_id, $meta);
        $attachment->update_meta('_focal_applied', $applied);

    }

    public function resize_dimensions ($output, $orig_w, $orig_h, $dest_w, $dest_h, $crop) {

        if (!$crop || !$this->focal) return $output;

        // core runs both of these guards after this filter (media.php:587, :660)
        if ($orig_w < $dest_w && $orig_h < $dest_h) return $output;

        [$crop_w, $crop_h, $new_w, $new_h] = static::calculate_crop($orig_w, $orig_h, $dest_w, $dest_h);

        if (wp_fuzzy_number_match($new_w, $orig_w) && wp_fuzzy_number_match($new_h, $orig_h)) return $output;

        return static::calculate_geometry($orig_w, $orig_h, $dest_w, $dest_h, $this->focal);

    }

    protected function get_cropped_sizes () {

        return array_filter(wp_get_registered_image_subsizes(), fn ($dims) => !empty($dims['crop']));

    }

    // GEOMETRY

    // Core's crop maths (media.php:611), so repositioned derivatives keep core's dimensions.
    public static function calculate_crop ($orig_w, $orig_h, $dest_w, $dest_h) {

        $new_w = min($dest_w, $orig_w);
        $new_h = min($dest_h, $orig_h);
        $ratio = max($new_w / $orig_w, $new_h / $orig_h);

        return [round($new_w / $ratio), round($new_h / $ratio), $new_w, $new_h];

    }

    public static function calculate_geometry ($orig_w, $orig_h, $dest_w, $dest_h, $focal) {

        [$crop_w, $crop_h, $new_w, $new_h] = static::calculate_crop($orig_w, $orig_h, $dest_w, $dest_h);

        $x = static::resolve_axis($focal[0], $orig_w, $crop_w);
        $y = static::resolve_axis($focal[1], $orig_h, $crop_h);

        return [0, 0, (int) $x['offset'], (int) $y['offset'], (int) $new_w, (int) $new_h, (int) $crop_w, (int) $crop_h];

    }

    public static function resolve_axis ($focal_pct, $orig, $crop) {

        $ideal  = round(static::to_pixels($focal_pct, $orig) - ($crop / 2));
        $offset = max(0, min($orig - $crop, $ideal));

        return [
            'offset'  => $offset,
            'cropped' => $crop < $orig,
            'clamped' => $offset != $ideal,
        ];

    }

    // null means emit nothing, so the pattern's own default survives
    public static function calculate_residual ($focal_pct, $orig, $crop) {

        $axis = static::resolve_axis($focal_pct, $orig, $crop);

        if (!$axis['cropped']) return $focal_pct;
        if (!$axis['clamped']) return null;

        return (static::to_pixels($focal_pct, $orig) - $axis['offset']) / $crop * 100;

    }

    protected static function to_pixels ($focal_pct, $length) {

        return $focal_pct / 100 * $length;

    }

}
