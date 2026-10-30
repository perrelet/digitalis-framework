<?php

namespace Digitalis;

abstract class Posts_Table extends Screen_Table {

    use Dependency_Injection;

    protected $post_type = 'post';

    public function run () {

        $this->slug = $this->post_type;

        parent::run();

        foreach ((array) $this->post_type as $post_type) add_action("manage_{$post_type}_posts_custom_column", [$this, 'post_column'], $this->priority, 2);

        //if (!in_array('publish_month', $this->get_filters())) add_filter('disable_months_dropdown', [$this, 'disable_months_dropdown'], 10, 2);
        add_action('restrict_manage_posts', [$this, 'render_filters_wrap']);
        add_action('pre_get_posts',         [$this, 'admin_controller']);
    
    }

    protected function get_columns_hook ($slug) {
    
        return "manage_edit-{$slug}_columns";
    
    }

    protected function get_column_hook ($slug) {
    
        // "manage_edit-{$post_type}_custom_column" doesn't exist. Use "manage_{$post_type}_posts_custom_column" instead.
        return false; 
    
    }

    protected function get_sortable_hook ($slug) {
    
        return "manage_edit-{$slug}_sortable_columns";
    
    }

    protected function get_row_actions_hook ($slug) {
    
        return ($slug == 'page') ? 'page_row_actions' : 'post_row_actions';
    
    }

    public function row_actions_wrap ($actions, $wp_post) {
    
        if (!in_array($wp_post->post_type, $this->slug)) return $actions;

        static::inject([$this, 'row_actions'], [&$actions, $wp_post]);

        return $actions;
    
    }

    public function post_column ($column, $post_id) {

        $call = [$this, "column_" . str_replace('-', '_', $column)];

        static::inject($call, [$post_id]);

    }

    public function disable_months_dropdown ($disable, $post_type) {

        return in_array($post_type, (array) $this->post_type, true) ? true : $disable;

    }

    public function render_filters_wrap () {
    
        if (!$this->is_admin_archive()) return;

        $this->render_filters();
    
    }

    public function admin_controller ($query) {

        if (!$this->is_main_admin_query($query)) return;

        $this->sort_columns($query);
        $this->filter_query($query);

    }

    public function sort_columns ($query) {
    
        if (!is_string($order_by = $query->get('orderby'))) return;

        $call = [$this, "sort_column_" . $order_by];
        if (is_callable($call)) {
            
            $qv = new Query_Vars();
            $qv->merge($query->query_vars);

            call_user_func($call, $qv);

            $query->query_vars = $qv->to_array();
        
        }
    
    }

    //

    protected function is_main_admin_query ($query) {

        return ($this->is_admin_archive() && $query->is_main_query() && isset($query->query_vars['post_type']) && in_array($query->query_vars['post_type'], (array) $this->post_type, true));

    }

    protected function is_admin_archive () {

        global $pagenow, $post_type;

        return (is_admin() && in_array($post_type, (array) $this->post_type, true) && ($pagenow == 'edit.php'));

    }

}