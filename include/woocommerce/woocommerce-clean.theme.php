<?php

namespace Digitalis;

abstract class Woocommerce_Clean_Theme extends Woocommerce_Theme {

    protected $do_account_icons = true;
    protected $do_wrap_elements = true;
    protected $do_modify_fields = true;
    protected $do_modify_cart   = true;

    protected $icon_library = 'iconoir';
    protected $template_overrides = [];

    protected $page_icons = [
        'dashboard'         => 'profile-circle',
        'orders'            => '3d-select-solid',
        'downloads'         => 'download',
        'edit-address'      => 'home-alt-slim-horiz',
        'payment-methods'   => 'card-security',
        'edit-account'      => 'key-alt',
        'customer-logout'   => 'log-out',
    ];

    protected $default_page_icon = 'circle';

    public function __construct() {

        add_filter('woocommerce_locate_template', [$this, 'locate_clean_template'], 10, 3);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_woo_clean_scripts']);

        if ($this->do_account_icons) $this->account_menu_icons();
        if ($this->do_wrap_elements) $this->wrap_woocommerce_elements();
        if ($this->do_modify_fields) $this->modify_fields();
        if ($this->do_modify_cart) $this->modify_cart();

        parent::__construct();
        
    }

    //

    public function locate_clean_template ($template, $template_name, $template_path) {

        if (in_array($template_name, $this->template_overrides)) return DIGITALIS_FRAMEWORK_PATH . 'templates/woocommerce-clean/' . $template_name;

        return $template;

    }

    //

    public function enqueue_woo_clean_scripts () {

        if ($this->do_account_icons) wp_enqueue_script('woo-clean', DIGITALIS_FRAMEWORK_URI . 'assets/js/woo-clean.js', [], DIGITALIS_FRAMEWORK_VERSION);

    }

    //

    public function account_menu_icons () {

        $this->template_overrides[] = 'myaccount/navigation.php';

        add_action('wp_enqueue_scripts', [$this, 'enqueue_account_menu_icons']);
        add_filter('woocommerce_account_menu_items', [$this, 'account_menu_items_add_icons'], PHP_INT_MAX);

        add_action('woocommerce_digitalis_account_navigation_classes', [$this, 'account_navigation_classes']);
        add_action('woocommerce_digitalis_before_account_navigation_loop', [$this, 'before_account_navigation_loop']);
        
        
    }

    public function enqueue_account_menu_icons () {

        if (!is_account_page()) return;

        switch ($this->icon_library) {

            case 'iconoir':
                wp_enqueue_style('iconoir-icons', 'https://cdn.jsdelivr.net/gh/iconoir-icons/iconoir@main/css/iconoir.css');
                break;

        }

    }

    public function account_menu_items_add_icons ($items) {

        if ($items) foreach ($items as $slug => $title) {

            $icon = $this->default_page_icon;;
            if (isset($this->page_icons[$slug])) $icon = $this->page_icons[$slug];
            if ($page = Woo_Account_Page::get_page($slug)) if ($page->get_icon()) $icon = $page->get_icon();
            $icon = apply_filters('digitalis_woocommerce_account_page_icon', $icon, $slug, $page);

            if (!$icon) continue;

            switch ($this->icon_library) {

                case 'iconoir':
                    $items[$slug] = "<i class='iconoir-{$icon}'></i><span>{$title}</span>";
                    break;

            }

        }

        return $items;

    }

    public function account_navigation_classes () {
        
        if (isset($_COOKIE['woo_clean_ui'])) {

            $state = json_decode(html_entity_decode(stripslashes($_COOKIE['woo_clean_ui'])), true);
            echo ($state['account_nav'] ?? false) ? '' : 'collapse';
        
        }
        
    }

    public function before_account_navigation_loop () {
        
        echo "<div class='nav-controls'>";
        echo "<i data-action='collapse-menu' class='control iconoir-cancel'></i>";
        echo "<i data-action='expand-menu' class='control iconoir-fast-arrow-right'></i>";
        echo "</div>";
        
    }

    //

    protected function wrap_woocommerce_elements () {

        add_action('woocommerce_checkout_before_customer_details', function () { echo "<div class='customer-details-wrap'><div class='before-customer-details-wrap'>"; }, PHP_INT_MIN);
        add_action('woocommerce_checkout_before_customer_details', function () { echo "</div><!-- .before-customer-details-wrap -->"; }, PHP_INT_MAX);

        add_action('woocommerce_checkout_after_customer_details', function () { echo "<div class='after-customer-details-wrap'>"; }, PHP_INT_MIN);
        add_action('woocommerce_checkout_after_customer_details', function () { echo "</div><!-- .after-customer-details-wrap --></div><!-- .after-customer-details-wrap -->"; }, PHP_INT_MAX);

        add_action('woocommerce_checkout_before_order_review_heading', function () { echo "<div class='checkout-order-review-wrap'>"; });
        add_action('woocommerce_checkout_after_order_review', function () { echo "</div><!-- .checkout-order-review-wrap -->"; });

    }

    protected function modify_fields () {

        add_filter('woocommerce_form_field', function ($field, $key, $args, $value) {

            return str_replace("woocommerce-input-wrapper", "woocommerce-input-wrapper woocommerce-input-wrapper-{$args['type']}", $field);

        }, 10, 4);

        add_filter('woocommerce_form_field_radio', function ($field, $key, $args, $value) {

            /* $field = str_replace("&lt;", "<", $field);
            $field = str_replace("&gt;", ">", $field);
            $field = str_replace("&quot;", '"', $field); */

            return htmlspecialchars_decode($field, ENT_QUOTES);

        }, 10, 4);

    }

    protected function modify_cart() {

        add_filter('woocommerce_quantity_input_type', [$this, 'quantity_input_type'], PHP_INT_MAX);

    }

    public function quantity_input_type ($type) {

        if ($type == 'hidden') echo "&nbsp";

        return $type;

    }

}