<?php

namespace Digitalis;

abstract class Woocommerce_Clean_Theme extends Woocommerce_Theme {

    protected $do_account_icons = true;
    protected $do_wrap_elements = true;

    protected $icon_library = 'iconoir';
    protected $template_overrides = [];

    protected $page_icons = [
        'dashboard'         => 'profile-circle',
        'orders'            => '3d-select-solid',
        'downloads'         => 'download',
        'edit-address'      => 'home-alt-slim-horiz',
        'edit-account'      => 'key-alt',
        'customer-logout'   => 'log-out',
    ];

    public function __construct() {

        add_filter('woocommerce_locate_template', [$this, 'locate_clean_template'], 10, 3);

        if ($this->do_account_icons) $this->account_menu_icons();
        if ($this->do_wrap_elements) $this->wrap_woocommerce_elements();

        parent::__construct();
        
    }

    //

    public function locate_clean_template ($template, $template_name, $template_path) {

        if (in_array($template_name, $this->template_overrides)) return DIGITALIS_FRAMEWORK_PATH . 'templates/woocommerce-clean/' . $template_name;

        return $template;

    }

    //

    public function account_menu_icons () {

        $this->template_overrides[] = 'myaccount/navigation.php';

        add_action('wp_enqueue_scripts', [$this, 'enqueue_account_menu_icons']);
        add_filter('woocommerce_account_menu_items', [$this, 'account_menu_items_add_icons'], PHP_INT_MAX);
        
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

        foreach ($this->page_icons as $slug => $page_icon) if (isset($items[$slug])) {
            
            switch ($this->icon_library) {

                case 'iconoir':
                    $items[$slug] = "<i class='iconoir-{$page_icon}'></i><span>{$items[$slug]}</span>";
                    break;

            }

        }

        return $items;

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

}