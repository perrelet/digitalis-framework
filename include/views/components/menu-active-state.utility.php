<?php

namespace Digitalis;

/**
 * Resolves is_current / is_ancestor on a coerced Menu_Item tree.
 * Spec: lattice/docs/specs/menu/ACTIVE_STATE.md.
 *
 * Per-item is_current / is_ancestor set explicitly before resolve() survive
 * untouched. The $explicit snapshot keeps that signal alive across matching
 * + bubble (the params themselves get overwritten during the passes).
 */
class Menu_Active_State extends Utility {

    // spl_object_id => ['is_current' => bool, 'is_ancestor' => bool]
    protected static $explicit = [];

    public static function resolve ($items, $config = []) {

        $config = array_merge([
            'ancestor_taxonomies' => null,
            'match_current'       => true,
            'match_ancestor'      => true,
        ], $config);

        static::$explicit = [];
        static::snapshot_explicit($items);

        $queried     = function_exists('get_queried_object') ? get_queried_object() : null;
        $current_url = static::current_url();

        static::walk_match($items, $queried, $current_url, $config);
        static::walk_bubble($items);

    }

    protected static function snapshot_explicit ($items) {

        foreach ($items as $item) {

            if (!($item instanceof Menu_Item)) continue;

            static::$explicit[spl_object_id($item)] = [
                'is_current'  => $item['is_current']  !== null,
                'is_ancestor' => $item['is_ancestor'] !== null,
            ];

            $submenu = $item['submenu'] ?? null;
            if ($submenu instanceof Menu) static::snapshot_explicit($submenu['items'] ?? []);

        }

    }

    protected static function was_explicit ($item, $key) {

        return static::$explicit[spl_object_id($item)][$key] ?? false;

    }

    protected static function walk_match ($items, $queried, $current_url, $config) {

        foreach ($items as $item) {

            if (!($item instanceof Menu_Item)) continue;

            static::match_item($item, $queried, $current_url, $config);

            // Nested Menus use their own config (propagated from parent unless overridden).
            $submenu = $item['submenu'] ?? null;
            if (!($submenu instanceof Menu)) continue;

            static::walk_match($submenu['items'] ?? [], $queried, $current_url, [
                'ancestor_taxonomies' => $submenu['ancestor_taxonomies'],
                'match_current'       => $submenu['match_current'],
                'match_ancestor'      => $submenu['match_ancestor'],
            ]);

        }

    }

    protected static function match_item ($item, $queried, $current_url, $config) {

        $allow_current  = $config['match_current']  && !static::was_explicit($item, 'is_current');
        $allow_ancestor = $config['match_ancestor'] && !static::was_explicit($item, 'is_ancestor');

        // Step 1 — Object-ID
        if ($allow_current && static::object_id_match($item, $queried)) {
            $item->is_current = true;
            return;
        }

        // Step 2 — URL exact
        if ($allow_current && static::url_exact_match($item, $current_url)) {
            $item->is_current = true;
            return;
        }

        // Step 3 — Content-ancestor
        if ($allow_ancestor && static::content_ancestor_match($item, $queried, $config)) {
            $item->is_ancestor = true;
            return;
        }

        // Step 4 — URL prefix
        if ($allow_ancestor && static::url_prefix_match($item, $current_url)) {
            $item->is_ancestor = true;
        }

    }

    protected static function object_id_match ($item, $queried) {

        $object_id   = $item['object_id'];
        $object_type = $item['object_type'];

        if ($object_id === null || $object_type === null) return false;
        if (!$queried)                                    return false;
        if (!function_exists('is_singular'))              return false;  // not in a WP request

        switch ($object_type) {

            case 'post_type':
                return is_singular() && (int) get_queried_object_id() === (int) $object_id;

            case 'taxonomy':
                $is_term_page = is_tax() || is_category() || is_tag();
                return $is_term_page && isset($queried->term_id) && (int) $queried->term_id === (int) $object_id;

            case 'post_type_archive':
                return is_post_type_archive((string) $object_id);

            default:
                return false;

        }

    }

    protected static function url_exact_match ($item, $current_url) {

        if (!$item['url']) return false;

        $normalised = static::normalise_url($item['url']);
        return $normalised !== null && $normalised === $current_url;

    }

    protected static function content_ancestor_match ($item, $queried, $config) {

        $object_id   = $item['object_id'];
        $object_type = $item['object_type'];

        if ($object_type === null || !$queried || !function_exists('is_singular')) return false;

        // Rule 1 — CPT-archive item, queried is singular of that CPT.
        if ($object_type === 'post_type_archive' && is_singular((string) $object_id)) {
            return true;
        }

        // Rule 2 — Taxonomy-term item, queried post has that term.
        if ($object_type === 'taxonomy' && is_singular() && $object_id) {

            $taxonomies = $config['ancestor_taxonomies'];
            if ($taxonomies === null) $taxonomies = static::all_hierarchical_taxonomies();

            foreach ((array) $taxonomies as $taxonomy) {
                if (has_term((int) $object_id, (string) $taxonomy, $queried)) return true;
            }

        }

        // Rule 3 — Taxonomy-term item, queried is a descendant term.
        if ($object_type === 'taxonomy' && isset($queried->term_id, $queried->taxonomy)) {

            $ancestors = get_ancestors((int) $queried->term_id, (string) $queried->taxonomy, 'taxonomy');
            if (in_array((int) $object_id, array_map('intval', (array) $ancestors), true)) return true;

        }

        // Rule 4 — CPT-archive item, queried is a term in a taxonomy applied to that CPT.
        if ($object_type === 'post_type_archive' && isset($queried->taxonomy)) {

            $taxonomy = get_taxonomy((string) $queried->taxonomy);
            if ($taxonomy && in_array((string) $object_id, (array) $taxonomy->object_type, true)) return true;

        }

        // Rule 5 — Hierarchical post-type item, queried is a descendant post.
        if ($object_type === 'post_type' && is_singular()) {

            $ancestors = get_post_ancestors((int) get_queried_object_id());
            if (in_array((int) $object_id, array_map('intval', (array) $ancestors), true)) return true;

        }

        return false;

    }

    protected static function url_prefix_match ($item, $current_url) {

        if (!$item['url']) return false;

        $normalised = static::normalise_url($item['url']);
        if ($normalised === null) return false;
        if ($normalised === '/')  return false;  // home is never an ancestor

        return str_starts_with($current_url, $normalised . '/');

    }

    // Post-order bubble. Returns true if any item in $items is marked
    // (current or ancestor) — caller uses this to mark ITS parent.
    protected static function walk_bubble ($items) {

        $any_marked = false;

        foreach ($items as $item) {

            if (!($item instanceof Menu_Item)) continue;

            $submenu        = $item['submenu'] ?? null;
            $sub_has_marked = $submenu instanceof Menu
                ? static::walk_bubble($submenu['items'] ?? [])
                : false;

            // Mark ancestor when a descendant is current/ancestor — unless
            // this item is already current, or is_ancestor was explicit.
            if (
                $sub_has_marked
                && $item['is_current'] !== true
                && !static::was_explicit($item, 'is_ancestor')
            ) {
                $item->is_ancestor = true;
            }

            if ($item['is_current'] === true || $item['is_ancestor'] === true) {
                $any_marked = true;
            }

        }

        return $any_marked;

    }

    public static function normalise_url ($url) {

        $url = trim($url);
        if ($url === '') return null;

        $url = preg_replace('/[?#].*$/', '', $url) ?? $url;

        if (preg_match('#^https?://#i', $url)) {

            $parsed_url  = parse_url($url);
            $parsed_home = function_exists('home_url') ? parse_url(home_url()) : null;

            if (!$parsed_home || !isset($parsed_url['host'], $parsed_home['host'])) return null;
            if (strcasecmp($parsed_url['host'], $parsed_home['host']) !== 0)        return null;

            $url = $parsed_url['path'] ?? '/';

        }

        $url = urldecode($url);
        $url = '/' . ltrim($url, '/');
        $url = rtrim($url, '/');
        if ($url === '') $url = '/';

        return $url;

    }

    public static function current_url () {

        $url = $_SERVER['REQUEST_URI'] ?? '/';

        return static::normalise_url($url) ?? '/';

    }

    protected static function all_hierarchical_taxonomies () {

        if (!function_exists('get_taxonomies')) return [];
        return array_values(get_taxonomies(['hierarchical' => true], 'names'));

    }

}
