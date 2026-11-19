<?php

namespace Digitalis\ACF;

use Digitalis\Attachment;
use Digitalis\Feature;

class Focal_Point extends Feature {

    // The point lent to the cutting under way: {id, point, w, h}.
    protected static $lent = null;

    // Per attachment, not per configuration, so only the first instance hooks them.
    protected static $hooked = false;

    protected static $recropping = null;

    protected $group_key    = 'group_focal_point';
    protected $title        = 'Framing';
    protected $location     = [[['param' => 'attachment', 'operator' => '==', 'value' => 'all']]];
    protected $instructions = 'Where the subject sits, as a percentage across and down the image. Leave blank to centre.';

    public function get_hooks () {

        $hooks = ['acf/include_fields' => 'register_fields'];

        if (static::$hooked) return $hooks;

        static::$hooked = true;

        return $hooks + [
            'acf/update_value/key=field_focal_x' => 'clamp_value',
            'acf/update_value/key=field_focal_y' => 'clamp_value',
            'wp_get_attachment_image_attributes' => 'add_focal_style',
            'acf/save_post'                      => ['recrop_sizes', 20],
            'image_resize_dimensions'            => 'resize_dimensions',
            'intermediate_image_sizes_advanced'  => 'lend_for_sizes',
            'wp_get_missing_image_subsizes'      => ['lend_for_missing', PHP_INT_MAX],
            'fallback_intermediate_image_sizes'  => 'stop_lending',
            'wp_generate_attachment_metadata'    => ['end_cut', PHP_INT_MAX],
            'wp_save_image_editor_file'          => 'clear_on_edit',
            'wp_get_attachment_image_src'        => ['version_image_src', 20],
            'wp_calculate_image_srcset'          => ['version_srcset', 20],
            'wp_content_img_tag'                 => ['version_content_image', 20],
            'wp_prepare_attachment_for_js'       => ['version_for_js', 20],
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
    public function clamp_value ($value, $post_id = 0, $field = []) {

        $value = is_numeric($value) ? max(0, min(100, (float) $value)) : $value;

        if (!is_numeric($post_id) || !is_array($cleared = get_post_meta((int) $post_id, '_focal_cleared', true))) return $value;

        // A form drawn before an Edit Image save posts the point that edit cleared.
        $posted = static::get_posted_pair();

        if ($posted === ['', '']) return $value;
        if ($posted === static::normalise_pair($cleared)) return '';

        delete_post_meta((int) $post_id, '_focal_cleared');

        return $value;

    }

    protected static function get_posted_pair () : ?array {

        $acf = wp_unslash($_POST['acf'] ?? []);

        if (!is_array($acf) || (!array_key_exists('field_focal_x', $acf) && !array_key_exists('field_focal_y', $acf))) return null;

        return static::normalise_pair([$acf['field_focal_x'] ?? '', $acf['field_focal_y'] ?? '']);

    }

    protected static function normalise_pair (array $pair) : array {

        return array_map(fn ($v) => is_numeric($v) ? round(max(0, min(100, (float) $v)), 1) : '', array_values($pair + ['', '']));

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

        if (is_numeric($post_id) && wp_attachment_is_image((int) $post_id)) static::recrop((int) $post_id);

    }

    // Cut as WordPress does: the edited file once edited, else the original, upright.
    public static function recrop (int $id) : bool {

        $attachment = Attachment::get_instance($id);
        $point      = $attachment?->get_focal_point();
        $edited     = static::is_edited($id);
        $file       = $edited ? get_attached_file($id) : (wp_get_original_image_path($id) ?: get_attached_file($id));
        $key        = ($point && $file) ? wp_basename($file) . ':' . implode(',', $point) : '';

        if (!$attachment || (string) $attachment->get_meta('_focal_applied') === $key) return false;

        $meta  = wp_get_attachment_metadata($id);
        $sizes = array_intersect_key(static::get_cropped_sizes(), (array) ($meta['sizes'] ?? []));

        if (!$file || !file_exists($file) || !$sizes || is_wp_error($editor = wp_get_image_editor($file))) return false;

        if (!$edited) $editor->maybe_exif_rotate();

        ['width' => $w, 'height' => $h] = $editor->get_size();

        static::$recropping = $id;
        static::$lent       = $point ? ['id' => $id, 'point' => $point, 'w' => $w, 'h' => $h] : null;

        try {

            $made = $editor->multi_resize($sizes);

            if (count($made) !== count($sizes)) return false;

            foreach ($made as $size => $data) $meta['sizes'][$size] = array_intersect_key($data, array_flip(['file', 'width', 'height', 'mime-type', 'filesize']));

            static::$lent = null;

            // Other plugins rebuild their copies of the sizes (WebP, AVIF) here.
            $meta = apply_filters('wp_generate_attachment_metadata', $meta, $id, 'update');

            wp_update_attachment_metadata($id, $meta);

        } finally {

            static::$recropping = null;
            static::$lent       = null;

        }

        $key ? $attachment->update_meta('_focal_applied', $key) : $attachment->delete_meta('_focal_applied');

        if ($point) $attachment->update_meta('_focal_framed', 1);

        return true;

    }

    // Edited: a full-orig backup that is no longer the attached file.
    public static function is_edited (int $id) : bool {

        $original = get_post_meta($id, '_wp_attachment_backup_sizes', true)['full-orig']['file'] ?? '';

        return $original && $original !== wp_basename((string) get_attached_file($id, true));

    }

    // LENDING

    public function lend_for_sizes ($sizes, $image_meta = [], $attachment_id = 0) {

        static::lend((int) $attachment_id, (array) $image_meta);

        return $sizes;

    }

    // Also asked by REST responses with nothing to cut.
    public function lend_for_missing ($sizes, $image_meta = [], $attachment_id = 0) {

        $sizes ? static::lend((int) $attachment_id, (array) $image_meta) : static::$lent = null;

        return $sizes;

    }

    public function stop_lending ($sizes) {

        static::$lent = null;

        return $sizes;

    }

    protected static function lend (int $id, array $image_meta) : void {

        static::$lent = null;

        // Regenerate and missing sizes read original_image, the picture from before an edit.
        if (!$id || (static::is_edited($id) && !empty($image_meta['original_image']))) return;
        if (empty($image_meta['width']) || empty($image_meta['height']) || !$point = Attachment::get_instance($id)?->get_focal_point()) return;

        static::$lent = ['id' => $id, 'point' => $point, 'w' => (int) $image_meta['width'], 'h' => (int) $image_meta['height']];

    }

    // No end can tell a full cut from a partial one, so only the recrop stamps the guard.
    public function end_cut ($metadata, $attachment_id = 0, $context = '') {

        $id = (int) $attachment_id;

        if ($id && static::$recropping !== $id && is_array($metadata)) {

            $lent  = ((static::$lent['id'] ?? 0) === $id) ? static::$lent : null;
            $pass  = $lent ? wp_basename($metadata['original_image'] ?? ($metadata['file'] ?? '')) . ':' . implode(',', $lent['point']) : '';
            $guard = (string) get_post_meta($id, '_focal_applied', true);

            if ($guard !== '' && $guard !== $pass) delete_post_meta($id, '_focal_applied');

        }

        static::$lent = null;

        return $metadata;

    }

    public function resize_dimensions ($output, $orig_w, $orig_h, $dest_w, $dest_h, $crop) {

        if (!$crop || !$lent = static::$lent) return $output;

        // Only the lent picture: one and a half pixels is a scale-down's rounding plus wp_constrain_dimensions()' bump.
        if (abs($orig_w * $lent['h'] - $orig_h * $lent['w']) > 1.5 * max($orig_w, $orig_h)) return $output;

        // core runs both of these guards after this filter (media.php:587, :660)
        if ($orig_w < $dest_w && $orig_h < $dest_h) return $output;

        [$crop_w, $crop_h, $new_w, $new_h] = static::calculate_crop($orig_w, $orig_h, $dest_w, $dest_h);

        if (wp_fuzzy_number_match($new_w, $orig_w) && wp_fuzzy_number_match($new_h, $orig_h)) return $output;

        return static::calculate_geometry($orig_w, $orig_h, $dest_w, $dest_h, $lent['point']);

    }

    // EDITING

    // Before the edited file is written, so Edit Image's own cutting goes centred.
    public function clear_on_edit ($override, $filename = '', $image = null, $mime_type = '', $post_id = 0) {

        $id = (int) $post_id;

        if (!$id || !($attachment = Attachment::get_instance($id)) || !$attachment->get_focal_point()) return $override;

        $attachment->update_meta('_focal_cleared', [(string) get_post_meta($id, 'focal_x', true), (string) get_post_meta($id, 'focal_y', true)]);
        delete_field('field_focal_x', $id);
        delete_field('field_focal_y', $id);
        $attachment->delete_meta('_focal_applied');

        return $override;

    }

    // ADDRESSES

    public function version_image_src ($image, $attachment_id = 0, $size = '', $icon = false) {

        if (is_array($image) && !empty($image[0])) $image[0] = static::version_url($image[0], (int) $attachment_id);

        return $image;

    }

    public function version_srcset ($sources, $size_array = [], $image_src = '', $image_meta = [], $attachment_id = 0) {

        if (is_array($sources)) foreach ($sources as $width => $source) $sources[$width]['url'] = static::version_url($source['url'], (int) $attachment_id, is_array($image_meta) ? $image_meta : null);

        return $sources;

    }

    public function version_content_image ($filtered_image, $context = '', $attachment_id = 0) {

        if (!$attachment_id || !preg_match('/ src="([^"]+)"/', $filtered_image, $match)) return $filtered_image;

        $url     = html_entity_decode($match[1], ENT_QUOTES);
        $current = static::version_url($url, (int) $attachment_id);

        return ($current === $url) ? $filtered_image : str_replace($match[0], ' src="' . esc_url($current) . '"', $filtered_image);

    }

    // The media library builds its size URLs by hand.
    public function version_for_js ($response, $attachment = null, $meta = null) {

        if (!empty($response['sizes']) && $attachment) foreach ($response['sizes'] as $size => $data) if (!empty($data['url'])) $response['sizes'][$size]['url'] = static::version_url($data['url'], (int) $attachment->ID, is_array($meta) ? $meta : null);

        return $response;

    }

    // A re-cut size keeps its name, and uploads are cached for years: its address carries its cut.
    public static function version_url (string $url, int $id, ?array $meta = null) : string {

        if (!$id || !get_post_meta($id, '_focal_framed', true)) return $url;

        $meta ??= wp_get_attachment_metadata($id);
        $file   = wp_basename(explode('?', $url, 2)[0]);

        foreach (array_keys(static::get_cropped_sizes()) as $size) {

            $entry = $meta['sizes'][$size] ?? null;

            if (!$entry || ($entry['file'] ?? '') !== $file) continue;

            $hash = substr(md5($entry['file'] . '|' . ($entry['filesize'] ?? '') . '|' . get_post_meta($id, 'focal_x', true) . ',' . get_post_meta($id, 'focal_y', true)), 0, 8);

            return add_query_arg('fp', $hash, remove_query_arg('fp', $url));

        }

        return $url;

    }

    protected static function get_cropped_sizes () {

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
