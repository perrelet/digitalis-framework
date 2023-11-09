<?php

namespace Digitalis;

abstract class Woo_Account_Page extends Singleton {

    protected $slug = 'account_page';
    protected $title = 'Account Page';
    protected $endpoint = null;
    protected $icon = null;
    protected $view = false;

    protected $active = true;
    protected $hidden = false;
    protected $capability = false;
    protected $position = 0;
    protected $parent = false;
    protected $collapsable = true;

    protected $children = [];
    
    protected static $static_init = false;

    protected static $pages = [];
    protected static $removed_pages = [];
    protected static $hidden_pages = [];
    protected static $renamed_pages = [];
    protected static $protected_pages = [];     
    protected static $page_positions = [];      // $slug => $position

    protected static $current_endpoint;

    public static function get_page ($slug) {

        return isset(self::$pages[$slug]) ? self::$pages[$slug] : null;

    }

    public static function remove_page ($slug) {

        self::$removed_pages[$slug] = $slug;

    }

    public static function hide_page ($slug) {

        self::$hidden_pages[$slug] = $slug;

    }

    public static function rename_page ($slug, $title) {

        self::$renamed_pages[$slug] = $title;

    }

    public static function position_page ($slug, $position) {

        self::$page_positions[$slug] = $position;

    }

    public static function get_current_endpoint () {

        if (is_null(self::$current_endpoint)) self::$current_endpoint = WC()->query->get_current_endpoint();
        return self::$current_endpoint;

    }

    //

    public function __construct () {

        self::$pages[$this->slug] = $this;

        if (is_null($this->endpoint)) $this->endpoint = $this->slug;

        add_action('init', [$this, 'init']);
        add_action('init', [$this, 'add_endpoints']);
        add_action('parse_request', [$this, 'maybe_add_permission_notice']);
        add_filter('woocommerce_get_query_vars', [$this, 'get_query_vars']);
        add_action('woocommerce_account_content', [$this, 'maybe_render_content'], 9);

    }

    public function init () {

        if (!self::$static_init) {
            
            self::static_init();
            self::$static_init = true;

        }

        if ($parent = $this->get_parent_page()) {

            $parent->add_child($this);

        }

        $position = ($parent) ? ($parent->get_position() + $this->position * 0.01) : $this->position;
        self::$page_positions[$this->slug] = $position;

    }

    public static function static_init () {

        add_filter('woocommerce_get_query_vars', [__CLASS__, 'get_query_vars_static']);
        add_filter('woocommerce_account_menu_items', [__CLASS__, 'account_menu_items_static']);

    }

    // OVERRIDE

    protected function render_before() {}
    protected function render() {}
    protected function render_after() {}

    // INTERNAL

    protected function add_child ($page) {

        $this->children[$page->get_slug()] = $page;

    }

    // GETTERS

    public function get_slug () {

        return $this->slug;

    }

    public function get_endpoint () {

        return $this->endpoint;

    }

    public function get_url ($value = '', $permalink = '') {
    
        return wc_get_endpoint_url($this->get_endpoint(), $value, $permalink);
    
    }

    public function get_title () {

        return $this->title;

    }

    public function get_position () {

        return $this->position;

    }

    public function get_icon () {

        return $this->icon;

    }

    public function get_capability () {

        return $this->capability;

    }

    public function get_parent () {

        return $this->parent;

    }

    public function get_parent_page () {

        return $this->parent ? self::get_page($this->parent) : false;

    }

    public function get_children () {

        return $this->children;

    }

    public function get_child ($slug) {

        return isset($this->children[$slug]) ? $this->children[$slug] : null;

    }

    public function get_collapsable () {

        return $this->collapsable;

    }

    // STATE

    public function is_active () {

        return (bool) $this->active;

    }

    public function is_hidden () {

        return (bool) $this->hidden;

    }

    public function is_protected () {

        return (bool) $this->capability;

    }

    public function can_access ($user = null) {

        if (!$this->is_protected()) return true; 

        if (is_null($user)) $user = wp_get_current_user();
        return $this->get_user_permission($user);

    }

    public function get_user_permission ($user) {

        return user_can($user, $this->capability);

    }

    public function is_current () {

        if (!is_account_page()) return false;
        return (self::get_current_endpoint() == ($this->slug == 'dashboard' ? false : $this->slug));

    }

    public function is_open () {

        if ($this->is_current()) return true;

        if ($parent = $this->get_parent_page()) {

            if ($parent->is_current()) return true;
            if ($children = $parent->get_children()) foreach ($children as $child) if ($child->is_current()) return true;

        }

        return false;

    }

    // HOOKS

	public function add_endpoints () {

        if ($this->active) add_rewrite_endpoint($this->endpoint, EP_ROOT | EP_PAGES);

	}

    public function maybe_add_permission_notice () {

        if (!$this->is_current()) return;

        if (!$this->can_access()) wc_add_notice('You do not have permission to access this page.', 'error');

    }

    public function get_query_vars ($vars) {

        if ($this->active) $vars[$this->slug] = $this->endpoint;

        return $vars;

    }

    // STATIC HOOKS

    public static function get_query_vars_static ($vars) {

        if (self::$removed_pages) foreach (self::$removed_pages as $slug) if (isset($vars[$slug])) unset($vars[$slug]);
        
        if ($vars) foreach ($vars as $slug => $endpoint) {

            if ($page = self::get_page($slug)) $vars[$slug] = $page->get_endpoint();

        }

        return $vars;

    }

    public static function account_menu_items_static ($old_items) {

        $positions = [];

        $position = 0;
        foreach ($old_items as $slug => $title) {

            $position++;
            $positions[$slug] = $position;

        }

        self::$page_positions = array_merge(self::$page_positions, $positions);
        asort(self::$page_positions, SORT_NUMERIC);

        //dprint(self::$page_positions);

        $items = [];

        foreach (self::$page_positions as $slug => $position) {

            if ($page = self::get_page($slug)) {

                $visible = true;

                if ($page->is_hidden() || !$page->is_active() || !$page->can_access()) $visible = false;
                if ($visible && ($parent = $page->get_parent_page()) && $parent->get_collapsable() && !$page->is_open()) $visible = false;

                if ($visible) $items[$page->get_endpoint()] = $page->get_title();

            } else if (isset($old_items[$slug])) {

                if (!isset(self::$hidden_pages[$slug]) && !isset(self::$removed_pages[$slug])) {
                    
                    $title = isset(self::$renamed_pages[$slug]) ? self::$renamed_pages[$slug] : $old_items[$slug];
                    $items[$slug] = $title;

                }

            }

        }

        return $items;

    }

    public function maybe_render_content () {

        if (!$this->is_current()) {

            remove_action('woocommerce_account_content', [$this, 'maybe_render_content']);
            return;

        }

        remove_action('woocommerce_account_content', 'woocommerce_account_content');

        if ($this->can_access()) {

            $this->render_before();

            $call = $this->view . '::render';
            is_callable($call) ? call_user_func($call) : $this->render();

            $this->render_after();

        }

    }

}