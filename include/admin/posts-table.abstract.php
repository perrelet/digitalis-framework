<?php

namespace Digitalis;

abstract class Posts_Table extends Admin_Table {

    protected $post_type = 'post';

    public function run () {
    
        add_filter("manage_{$this->post_type}_posts_columns",       [$this, 'columns_wrap']);
        add_action("manage_{$this->post_type}_posts_custom_column", [$this, 'column'], 10, 2);

        //if (!in_array('publish_month', $this->get_filters())) add_filter('disable_months_dropdown', [$this, 'disable_months_dropdown'], 10, 2);
        add_action('restrict_manage_posts', [$this, 'render_filters_wrap']);
        add_action('pre_get_posts',         [$this, 'admin_controller']);
    
    }

    protected $columns;

    public function columns_wrap ($columns) {

        $this->columns = &$columns;
        $this->columns($this->columns);

        return $columns;
        
    }

    public function columns (&$columns) {

        // ...

    }

    protected function remove_column ($key) {
    
        if (isset($this->columns[$key])) unset($this->columns[$key]);
    
    }

    protected function insert_column ($entry, $position = 0, $after = true) {

        if (!is_int($position)) $position = array_search($position, array_keys($this->columns));

        if ($position === false) return;
        if (!is_array($entry))   return;
        if ($after) $position++;

        $this->columns =
            array_slice($this->columns, 0, $position, true) +
            $entry +
            array_slice($this->columns, $position, count($this->columns) - 1, true)
        ;
    
    }

    protected function append_column ($entry, $label = null) {

        if (!is_array($entry)) $entry = [$entry => $label];
        $this->columns += $entry;

    }

    protected function prepend_column ($entry, $label) {

        if (!is_array($entry)) $entry = [$entry => $label];
        $this->insert_column($entry, 1, false);

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