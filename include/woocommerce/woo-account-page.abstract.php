<?php

namespace Digitalis;

abstract class Woo_Account_Page {

    protected $slug = 'account_page';
    protected $title = 'Account Page';
    protected $endpoint = null;
    protected $icon = null;

    protected $active = true;
    protected $hidden = false;
    protected $capability = false;
    protected $position = 0;
    
    protected static $static_init = false;

    protected static $pages = [];
    protected static $removed_pages = [];
    protected static $hidden_pages = [];
    protected static $renamed_pages = [];
    protected static $protected_pages = [];     
    protected static $page_positions = [];      // $slug => $position

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

    //

    public function __construct () {

        if (!self::$static_init) {
            
            self::static_init();
            self::$static_init = true;

        }

        self::$pages[$this->slug] = $this;

        if (is_null($this->endpoint)) $this->endpoint = $this->slug;
        if (is_numeric($this->position)) self::$page_positions[$this->slug] = $this->position;

        add_action('init', [$this, 'add_endpoints']);
        add_action('parse_request', [$this, 'maybe_add_permission_notice']);
        add_filter('woocommerce_get_query_vars', [$this, 'get_query_vars']);
        add_action('woocommerce_account_content', [$this, 'maybe_render_content'], 9);

    }

    public static function static_init () {

        add_filter('woocommerce_get_query_vars', [__CLASS__, 'get_query_vars_static']);
        add_filter('woocommerce_account_menu_items', [__CLASS__, 'account_menu_items_static']);

    }

    // OVERRIDE

    protected function render() {}

    // GETTERS

    public function get_slug () {

        return $this->slug;

    }

    public function get_endpoint () {

        return $this->endpoint;

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

        if (is_null($user)) $user = wp_get_current_user();
        return $this->is_protected() ? user_can($user, $this->capability) : true;

    }

    public function is_current () {

        if (!is_account_page()) return false;
        return (WC()->query->get_current_endpoint() == ($this->slug == 'dashboard' ? false : $this->slug));

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
                
                if (!$page->is_hidden() && $page->is_active() && $page->can_access()) {
                    
                    $items[$page->get_endpoint()] = $page->get_title();

                }

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

        if ($this->can_access()) $this->render();

    }

}