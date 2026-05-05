<?php

namespace Digitalis;

abstract class Attachment_Table extends Screen_Table {

    use Dependency_Injection;

    public function run () {

        $this->slug = 'attachment';

        parent::run();

        add_action('manage_media_custom_column', [$this, 'post_column'], $this->priority, 2);

        add_action('restrict_manage_posts', [$this, 'render_filters_wrap']);
        add_action('pre_get_posts',         [$this, 'admin_controller']);

    }

    protected function get_columns_hook ($slug) {

        return 'manage_media_columns';

    }

    protected function get_column_hook ($slug) {

        return false; // "manage_media_custom_column" is echo-style ($column, $post_id) — hooked separately in run().

    }

    protected function get_sortable_hook ($slug) {

        return 'manage_upload_sortable_columns';

    }

    protected function get_row_actions_hook ($slug) {

        return 'media_row_actions';

    }

    public function row_actions_wrap ($actions, $wp_post) {

        if ($wp_post->post_type !== 'attachment') return $actions;

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

        if (!$order_by = $query->get('orderby')) return;

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

        return ($this->is_admin_archive() && $query->is_main_query() && isset($query->query_vars['post_type']) && ($query->query_vars['post_type'] == 'attachment'));

    }

    protected function is_admin_archive () {

        global $pagenow;

        return (is_admin() && ($pagenow == 'upload.php'));

    }

}
