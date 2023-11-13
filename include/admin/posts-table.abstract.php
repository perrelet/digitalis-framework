<?php

namespace Digitalis;

abstract class Posts_Table extends Admin_Table {

    protected $post_type = 'post';

    public function run () {
    
        add_filter("manage_{$this->post_type}_posts_columns",       [$this, 'columns']);
        add_action("manage_{$this->post_type}_posts_custom_column", [$this, 'column'], 10, 2);

        //if (!in_array('publish_month', $this->get_filters())) add_filter('disable_months_dropdown', [$this, 'disable_months_dropdown'], 10, 2);
        add_action('restrict_manage_posts', [$this, 'render_filters_wrap']);
        add_action('pre_get_posts',         [$this, 'admin_controller']);
    
    }

    public function columns ($columns) {

        return $columns;
        
    }

    public function column ($column, $post_id) {

        $call = [$this, "column_" . str_replace('-', '_', $column)];

        if (is_callable($call)) call_user_func($call, $post_id);

    }

    public function disable_months_dropdown ($disable, $post_type) {

        return ($post_type == $this->post_type) ? true : $disable;

    }

    public function render_filters_wrap () {
    
        if (!$this->is_admin_archive()) return;

        $this->render_filters();
    
    }

    public function admin_controller ($query) {

        if (!$this->is_main_admin_query($query)) return;

        $this->filter_query($query);

    }

    protected function is_main_admin_query ($query) {

        return ($this->is_admin_archive() && $query->is_main_query() && isset($query->query_vars['post_type']) && ($query->query_vars['post_type'] == $this->post_type));

    }

    protected function is_admin_archive () {

        global $pagenow, $post_type;

        return (is_admin() && ($post_type == $this->post_type) && ($pagenow == 'edit.php'));

    }

}