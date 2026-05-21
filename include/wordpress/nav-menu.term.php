<?php

namespace Digitalis;

class Nav_Menu extends Term {

    protected static $taxonomy = 'nav_menu';

    public static function extract_id ($data = null) {

        if (is_string($data) && !is_numeric($data) && function_exists('wp_get_nav_menu_object')) {

            $wp_term = wp_get_nav_menu_object($data);
            if ($wp_term && !is_wp_error($wp_term)) return (int) $wp_term->term_id;

        }

        return parent::extract_id($data);

    }

    public function get_items () {

        if (!function_exists('wp_get_nav_menu_items')) return [];

        $wp_items = wp_get_nav_menu_items($this->get_id());
        if (!$wp_items) return [];

        $items = [];
        foreach ($wp_items as $wp_item) $items[] = Nav_Menu_Item::get_instance($wp_item->ID);

        return $items;

    }

    public function get_items_tree () {

        $items = $this->get_items();
        if (!$items) return [];

        $flat = [];
        foreach ($items as $item) $flat[$item->get_id()] = $item->as_menu_item_params();

        $children_of = [];
        foreach ($items as $item) $children_of[$item->get_menu_parent_id()][] = $item->get_id();

        return static::assemble_tree(0, $flat, $children_of);

    }

    protected static function assemble_tree ($parent_id, $flat, $children_of) {

        $result = [];

        foreach ($children_of[$parent_id] ?? [] as $child_id) {

            $item         = $flat[$child_id];
            $sub_children = static::assemble_tree($child_id, $flat, $children_of);

            if ($sub_children) $item['submenu'] = ['items' => $sub_children];

            $result[] = $item;

        }

        return $result;

    }

}
