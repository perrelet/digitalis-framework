<?php

namespace Digitalis;

use WP_Query;

abstract class WC_Orders_Table extends Screen_Table {

    protected $slug = 'woocommerce_page_wc-orders';
    protected $priority = 20;

    public function run () {

        add_action("manage_{$this->slug}_custom_column",                    [$this, 'order_column'], $this->priority, 2);

        add_action('woocommerce_order_list_table_restrict_manage_orders',   [$this, 'render_filters_wrap'], 25, 2);
        add_action('restrict_manage_posts',                                 [$this, 'render_filters_wrap'], 25, 2);

        add_filter('woocommerce_order_list_table_prepare_items_query_args', [$this, 'hpos_query']);
        add_filter('pre_get_posts',                                         [$this, 'cpt_query']);
    
        return parent::run();
    
    }

    protected function get_column_hook ($slug) {
    
        return false;
    
    }

    public function order_column ($column, $order) {

        echo $this->column('', $column, $order);
    
    }

    public function render_filters_wrap ($post_type, $which = 'top') {
    
        if ($post_type != 'shop_order') return;

        $this->render_filters();
    
    }

    public function hpos_query ($args) {

        $query_vars = $this->query_vars(new Query_Vars($args));
        return ($query_vars instanceof Query_Vars) ? $query_vars->to_array() : $args;

    }

    public function cpt_query (WP_Query $wp_query) {
    
        global $pagenow;
    
        if (!is_admin() || !$wp_query->is_main_query())                              return;
        if (($pagenow != 'edit.php') || $wp_query->get('post_type') != 'shop_order') return;

        $query_vars = $this->query_vars(new Query_Vars($wp_query->query_vars));
        if ($query_vars instanceof Query_Vars) $wp_query->query_vars = $query_vars->to_array();
    
    }

    public function query_vars ($query_vars) {

        return $query_vars;

    }

}