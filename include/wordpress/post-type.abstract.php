<?php

namespace Digitalis;

abstract class Post_Type extends Base {

    protected $slug         = 'post-type';
    protected $archive      = 'post-types';
    protected $icon         = 'dashicons-format-aside';

    protected $text_domain  = 'default';
    protected $singular     = 'Post Type';
    protected $plural       = 'Post Types';

    protected $filters      = [];                               // key => type (taxonomy | acf) || key => [type => $type, args => [ ... ], ...] || 'months_dropdown'

    //

    protected $post_type;
    protected $removed_supports = [];

    public function __construct ($flush = false) {

        if ($flush) flush_rewrite_rules();
        
        add_action('init', [$this, 'register']);

        // Admin

        if (method_exists($this, 'columns'))        add_filter("manage_{$this->slug}_posts_columns", [$this, 'columns']);
        if (method_exists($this, 'column'))         add_action("manage_{$this->slug}_posts_custom_column", [$this, 'column'], 10, 2);

        if (!in_array('publish_month', $this->filters)) add_filter('disable_months_dropdown',   [$this, 'disable_months_dropdown'], 10, 2);
        add_action('restrict_manage_posts',     [$this, 'render_filters']);
        add_action('pre_get_posts',             [$this, 'admin_controller']);

        if (method_exists($this, 'after_insert'))   add_action("wp_after_insert_post", [$this, 'after_insert_wrap'], 10, 4);

        if (method_exists($this, 'admin_query'))    add_action('pre_get_posts', [$this, 'admin_query_wrap']);

        // Front

        if (method_exists($this, 'main_query'))     add_action('pre_get_posts', [$this, 'main_query_wrap']);

        $this->run();

    }

    public function run () {}

    public function register () {

        $args               = $this->get_args($this->get_default_args());
        $args['rewrite']    = $this->get_rewrite($this->get_default_rewrite());
        $args['supports']   = $this->get_supports($this->get_default_supports());
        $args['labels']     = $this->get_labels($this->get_default_labels());

        if ($this->removed_supports) $args['supports'] = array_diff($args['supports'], $this->removed_supports);

        $args = apply_filters("digitalis-" . $this->get_identifier() . "-args", $args);

        $this->post_type = register_post_type(
            $this->slug,
            $args
        );
        
    }

    //

    protected function remove_support ($support) {

        $this->removed_supports[] = $support;

    }

    //

    protected function get_args ($args) {           // You may wish to override this...

        return $args;

    }

    protected function get_rewrite ($rewrite) {     // and this...

        return $rewrite;

    }

    protected function get_supports ($supports) {   // this one to

        return $supports;

    }
   
    protected function get_labels ($labels) {       // ..also me.

        return $labels;

    }

    protected function get_default_args () {

        return [
            'description'           => __($this->plural, $this->text_domain),
            'public'                => true,
            'publicly_queryable'    => true,
            'menu_position'         => 5,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_icon'             => $this->icon,
            'can_export'            => true, 
            'delete_with_user'      => false,
            'hierarchical'          => false,
            'has_archive'           => $this->archive,
            'map_meta_cap'          => true,
            'show_in_rest'          => true
        ];

    }

    protected function get_default_rewrite () {

        return apply_filters("digitalis-" . $this->get_identifier() . "-rewrite",
        [
            'slug'          => $this->archive,
            'with_front'    => false,
            'pages'         => true,
            'feeds'         => true
        ]);

    }

    protected function get_default_supports () {

        return apply_filters("digitalis-" . $this->get_identifier() . "-supports",
        [
            'title',
            'editor',
            'thumbnail',
            'custom-fields',
            'excerpt',
            'comments'
        ]);

    }

    protected function get_default_labels () {

        return apply_filters("digitalis-" . $this->get_identifier() . "-labels",
        [
            'name'               => __( $this->plural,                          $this->text_domain ),
            'singular_name'      => __( $this->singular,                        $this->text_domain ),
            'menu_name'          => __( $this->plural,                          $this->text_domain ),
            'name_admin_bar'     => __( $this->plural,                          $this->text_domain ),
            'add_new'            => __( "Add New {$this->singular}",            $this->text_domain ),
            'add_new_item'       => __( "Add New {$this->singular}",            $this->text_domain ),
            'edit_item'          => __( "Edit {$this->singular}",               $this->text_domain ),
            'new_item'           => __( "New {$this->singular}",                $this->text_domain ),
            'view_item'          => __( "View {$this->singular}",               $this->text_domain ),
            'search_items'       => __( "Search {$this->plural}",               $this->text_domain ),
            'not_found'          => __( "No {$this->plural} found",             $this->text_domain ),
            'not_found_in_trash' => __( "No {$this->plural} found in trash",    $this->text_domain ),
            'all_items'          => __( $this->plural,                          $this->text_domain ),
            'archive_title'      => __( $this->plural,                          $this->text_domain ),
        ]);

    }

    //

    public function main_query_wrap ($query) {

        if (!$this->is_main_query($query)) return;

        $this->main_query($query);

    }

    //

    protected function get_filters () {

        if ($this->filters) foreach ($this->filters as $key => &$filter) {

            if (!is_array($filter)) $filter = ['type'  => $filter];

            $filter = wp_parse_args($filter, [
                'type'  => 'taxonomy',
                'name'  => $key,
                'args'  => [],
            ]);

            switch ($filter['type']) {

                case 'taxonomy':

                    $taxonomy = get_taxonomy($key);

                    $filter['args'] = wp_parse_args($filter['args'], [
                        'taxonomy'          => $key,
                        'name'              => "tax[{$filter['name']}]",
                        'value_field'       => 'term_id',
                        'hide_empty'        => true,
                        'hide_if_empty'     => true,
                        'selected'          => $_REQUEST['tax'][$key] ?? false,
                        'show_option_none'  => "Filter by {$taxonomy->labels->singular_name}",
                        'option_none_value' => 0,
                    ]);

                    break;

                case 'acf':

                    $filter['args'] = wp_parse_args($filter['args'], [
                        'hide_falsy'    => true,
                        'compare'       => '=',
                    ]);

            }

        }

        return $this->filters;

    }

    public function admin_controller ($query) {

        if (!$this->is_main_admin_query($query)) return;

        $this->get_filters();

        if ($this->filters) foreach ($this->filters as $key => $filter) {

            $value = false;

            switch ($filter['type']) {

                case 'taxonomy':

                    if ($value = $_REQUEST['tax'][$key] ?? false) {

                        if (!isset($query->query_vars['tax_query'])) $query->query_vars['tax_query'] = [];

                        $query->query_vars['tax_query'][] = [
                            'taxonomy' => $key,
                            'field'    => $filter['args']['value_field'] == 'term_id' ? 'id' : $filter['args']['value_field'],
                            'terms'    => $value,
                        ];

                    }

                    break;

                case 'acf':

                    if ($value = $_REQUEST['meta'][$key] ?? false) {

                        if (!isset($query->query_vars['meta_query'])) $query->query_vars['meta_query'] = [];

                        $query->query_vars['meta_query'][] = [
                            'key'     => $key,
                            'value'   => $value,
                            'compare' => $filter['args']['compare'],
                        ];

                    }

                    break;

            }

        }

    }

    public function disable_months_dropdown ($disable, $post_type) {

        return ($post_type == $this->slug) ? true : $disable;

    }

    public function render_filters () {

        if (!$this->is_admin_archive()) return;

        if (method_exists($this, 'before_render_filters')) $this->before_render_filters();

        if ($this->filters) foreach ($this->filters as $key => $filter) {

            switch ($filter['type']) {

                case 'taxonomy':

                    wp_dropdown_categories($filter['args']);
                    
                    break;

                case 'acf':

                    $current_value = $_REQUEST['meta'][$key] ?? false;

                    $post_ids = (new \WP_Query([
                        'post_type'         => $this->slug,
                        'post_status'       => 'any',
                        'posts_per_page'    => 1,
                        'fields'            => 'ids',
                    ]))->get_posts();

                    if (!$post_ids) break;
                    if (!$field = get_field_object($key, $post_ids[0])) break;

                    if (isset($field['choices']) && ($options = $field['choices'])) {

                        echo "<select name='meta[{$filter['name']}]'>";

                            echo "<option value='0'>Filter by {$field['label']}</option>";

                            foreach ($options as $value => $label) {

                                if ($filter['args']['hide_falsy'] && !$value) continue;
                                echo "<option value='{$value}' " . selected($value, $current_value, false) . ">{$label}</option>";

                            }

                        echo "</select>";
                    } else {

                        // Text field

                    }

                    break;

            }

        }

        if (method_exists($this, 'after_render_filters')) $this->after_render_filters();

    }

    public function after_insert_wrap ($post_id, $post, $update, $post_before) {

        if ($post->post_type != $this->slug) return;

        $this->after_insert($post_id, $post, $update, $post_before);

    }

    public function admin_query_wrap ($query) {

        if (!$this->is_main_admin_query($query)) return;

        $this->admin_query($query);

    }

    //

    protected function is_main_query ($query) {

        return (!is_admin() && $query->is_main_query() && ($query->get('post_type') == $this->slug));

    }

    protected function is_main_admin_query ($query) {

        return ($this->is_admin_archive() && $query->is_main_query() && isset($query->query_vars['post_type']) && ($query->query_vars['post_type'] == $this->slug));

    }

    protected function is_admin_archive () {

        global $pagenow, $post_type;

        return (is_admin() && ($post_type == $this->slug) && ($pagenow == 'edit.php'));

    }

    //

    /* public function columns ($columns) {

        return $columns;
        
    }

    public function column ($column, $post_id) {

        switch ($column) {

            case '':
                return;

        }

    } */

}