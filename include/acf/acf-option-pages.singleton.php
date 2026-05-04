<?php

namespace Digitalis;

class ACF_Option_Pages extends Singleton {

    // Defaults for every page; per-page entries override. Null values = let ACF derive or use its own default (key is filtered before passing to acf_add_options_page).

    protected $defaults = [
        'page_title'      => null,             // Required — set per page. Page <title> and H1.
        'menu_title'      => null,             // Falls back to page_title.
        'menu_slug'       => null,             // Falls back to sanitize_title(page_title).
        'parent_slug'     => null,             // Auto-wired for sub-pages.
        'position'        => null,             // null = after Comments in menu order. Top-level only.
        'icon_url'        => null,             // null = generic gear (dashicons-admin-generic). Top-level only.
        'redirect'        => true,             // Parent menu click → first sub-page.
        'post_id'         => null,             // 'options' by default; sub-pages inherit from parent unless overridden.
        'autoload'        => false,            // Load all field values into WP's autoloaded options on every request.
        'capability'      => 'manage_options', // Overrides ACF's 'edit_posts' default — options pages are admin-only.
        'update_button'   => 'Update',
        'updated_message' => 'Options Updated',
    ];

    protected $page      = [];
    protected $sub_pages = [];

    public function init () {

        if (!function_exists('acf_add_options_page')) return;

        add_action('acf/init', [$this, 'acf_init']);

    }

    public function acf_init () {

        if (!$this->page) return;
        if (!$parent = acf_add_options_page($this->prepare($this->page))) return;

        foreach ($this->sub_pages as $sub) {

            $args = array_merge(['parent_slug' => $parent['menu_slug']], $sub);
            acf_add_options_page($this->prepare($args));

        }

    }

    protected function prepare ($args) {

        return array_filter(array_merge($this->defaults, $args), fn ($v) => !is_null($v));

    }

}
