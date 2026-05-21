<?php

namespace Digitalis;

class Nav_Menu_Item extends Post {

    protected static $post_type = 'nav_menu_item';

    protected function prepare_wp_post ($wp_post) {

        if (isset($wp_post->url)) return $wp_post;

        return wp_setup_nav_menu_item($wp_post);

    }

    public function get_text () {

        return (string) ($this->wp_post->title ?? '');

    }

    public function get_url ($leavename = false) {

        return !empty($this->wp_post->url) ? $this->wp_post->url : null;

    }

    public function get_target () {

        return !empty($this->wp_post->target) ? $this->wp_post->target : null;

    }

    public function get_description () {

        // Read post_content directly — bypass wp_setup_nav_menu_item's
        // fallback chain (post type description / labels->archives etc.).
        // Also suppress descriptions that duplicate the title, which is
        // what WP stores into post_content for archive items added via
        // admin. Both honour "editor left it blank" as no description.
        $explicit = trim((string) ($this->wp_post->post_content ?? ''));
        if ($explicit === '')                return null;
        if ($explicit === $this->get_text()) return null;
        return $explicit;

    }

    public function get_object_id () {

        return !empty($this->wp_post->object_id) ? (int) $this->wp_post->object_id : null;

    }

    public function get_object_type () {

        return match ($this->wp_post->type ?? null) {
            'post_type' => 'post_type',
            'taxonomy'  => 'taxonomy',
            default     => 'custom',
        };

    }

    public function get_menu_parent_id () {

        return (int) ($this->wp_post->menu_item_parent ?? 0);

    }

    public function get_menu_order () {

        return (int) ($this->wp_post->menu_order ?? 0);

    }

    public function get_menu_classes () {

        return array_values(array_filter(array_map('trim', (array) ($this->wp_post->classes ?? []))));

    }

    public function as_menu_item_params () {

        $item = [
            'text'        => $this->get_text(),
            'url'         => $this->get_url(),
            'object_id'   => $this->get_object_id(),
            'object_type' => $this->get_object_type(),
            'wp_post'     => $this->wp_post,
        ];

        if (($target = $this->get_target()) !== null)           $item['target']      = $target;
        if (($description = $this->get_description()) !== null) $item['description'] = $description;
        if ($classes = $this->get_menu_classes())               $item['classes']     = $classes;

        if ($item['object_type'] === 'custom' && $item['url']) {

            $archive_post_type = static::detect_post_type_archive($item['url']);

            if ($archive_post_type !== null) {
                $item['object_type'] = 'post_type_archive';
                $item['object_id']   = $archive_post_type;
            }

        }

        return $item;

    }

    protected static function detect_post_type_archive ($url) {

        if (!function_exists('get_post_types')) return null;

        $url_normalised = Menu_Active_State::normalise_url($url);
        if ($url_normalised === null) return null;

        foreach (get_post_types(['has_archive' => true], 'names') as $post_type) {

            $archive_url = get_post_type_archive_link($post_type);
            if (!$archive_url) continue;

            if (Menu_Active_State::normalise_url($archive_url) === $url_normalised) return $post_type;

        }

        return null;

    }

}
