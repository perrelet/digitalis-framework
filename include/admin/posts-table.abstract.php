<?php

declare(strict_types=1);

namespace Lattice;

abstract class Posts_Table extends Screen_Table {

    use Dependency_Injection;

    protected $post_type = 'post';

    public function get_acf_field ($name) {

        $post_ids = (new \WP_Query([
            'post_type'      => $this->post_type,
            'post_status'    => 'any',       // the table lists every status; a type whose posts were all drafts resolved no field
            'meta_key'       => "_{$name}",  // get_field_object() resolves a name through the post's ACF reference meta alone, which the newest post (an import, a REST draft) may not carry
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]))->get_posts();

        return get_field_object($name, $post_ids ? $post_ids[0] : false);

    }

    public function run () {

        $this->slug = $this->post_type;

        parent::run();

        add_action("manage_{$this->post_type}_posts_custom_column", [$this, 'post_column'], $this->priority, 2);

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

        return ($this->is_admin_archive() && $query->is_main_query() && isset($query->query_vars['post_type']) && ($query->query_vars['post_type'] == $this->post_type));

    }

    protected function is_admin_archive () {

        global $pagenow, $post_type;

        return (is_admin() && ($post_type == $this->post_type) && ($pagenow == 'edit.php'));

    }

}