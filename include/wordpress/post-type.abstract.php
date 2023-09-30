<?php

namespace Digitalis;

abstract class Post_Type extends Singleton {

    protected $slug         = 'post-type';
    protected $archive      = 'post-types';
    protected $icon         = 'dashicons-format-aside';

    protected $text_domain  = 'default';
    protected $singular     = 'Post Type';
    protected $plural       = 'Post Types';

    protected $model_class  = false;
    protected $register     = true;

    protected $filters      = [];                               // key => type (taxonomy | acf) || key => [type => $type, args => [ ... ], ...] || 'months_dropdown'

    //

    protected $post_type;
    protected $removed_supports = [];

    public function init () {

        //if ($flush) flush_rewrite_rules();
        
        if ($this->register) add_action('init', [$this, 'register']);
        add_action('template_redirect', [$this, 'instantiate_model']);

        // Admin

        add_action('pre_get_posts', [$this, 'admin_query_wrap']);

        if (method_exists($this, 'columns'))        add_filter("manage_{$this->slug}_posts_columns", [$this, 'columns']);
        if (method_exists($this, 'column'))         add_action("manage_{$this->slug}_posts_custom_column", [$this, 'column'], 10, 2);

        if (!in_array('publish_month', $this->get_filters())) add_filter('disable_months_dropdown',   [$this, 'disable_months_dropdown'], 10, 2);
        add_action('restrict_manage_posts',     [$this, 'render_filters']);
        add_action('pre_get_posts',             [$this, 'admin_controller']);

        if (method_exists($this, 'after_insert'))   add_action('wp_after_insert_post',  [$this, 'after_insert_wrap'], 10, 4);

        // Front

        add_action('query_vars',    [$this, 'register_query_vars_wrap']);
        add_action('pre_get_posts', [$this, 'main_query_wrap']);

        if (method_exists($this, 'ajax_query')) {
            
            $action = "query_" . $this->slug;
            add_action("wp_ajax_{$action}",         [$this, 'ajax_query_wrap']);
            add_action("wp_ajax_nopriv_{$action}",  [$this, 'ajax_query_wrap']);

        }

        $this->run();

    }

    public function run () {}  // Override me :)

    public function register () {

        $args               = $this->get_args($this->get_default_args());
        $args['rewrite']    = $this->get_rewrite($this->get_default_rewrite());
        $args['supports']   = $this->get_supports($this->get_default_supports());
        $args['labels']     = $this->get_labels($this->get_default_labels());

        if ($this->removed_supports) $args['supports'] = array_diff($args['supports'], $this->removed_supports);

        $args = apply_filters("Digitalis/Post_Type/" . static::class . "/Args", $args);

        $this->post_type = register_post_type(
            $this->slug,
            $args
        );
        
    }

    public function instantiate_model () {

        global $wp_query;

        if ($this->model_class && $wp_query && $wp_query->is_single && $this->is_main_query($wp_query)) {

            $call = "{$this->model_class}::get_instance";

            if (is_callable($call) && ($instance = call_user_func($call))) {
                
                //while (isset($GLOBALS[$global_var])) $global_var = "_{$global_var}";
                $GLOBALS[$instance->get_global_var()] = $instance;

            }

        }
        
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

        return apply_filters("Digitalis/Post_Type/" . static::class . "/Rewrite",
        [
            'slug'          => $this->archive,
            'with_front'    => false,
            'pages'         => true,
            'feeds'         => true
        ]);

    }

    protected function get_default_supports () {

        return apply_filters("Digitalis/Post_Type/" . static::class . "/Supports",
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

        return apply_filters("Digitalis/Post_Type/" . static::class . "/Labels",
        [
            'name'               => __( $this->plural,                          $this->text_domain ),
            'singular_name'      => __( $this->singular,                        $this->text_domain ),
            'menu_name'          => __( $this->plural,                          $this->text_domain ),
            'name_admin_bar'     => __( $this->singular,                        $this->text_domain ),
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

    public function call_model ($method, $default = [], $args = []) {

        $call = false;

        if ($this->model_class)             $call = $this->model_class . "::" . $method;
        if (!$call || !is_callable($call))  $call = static::class . "::" . $method;
        if (!$call || !is_callable($call))  $call = [$this, $method];      

        return is_callable($call) ? call_user_func_array($call, $args) : $default;
        
    }

    public function query_vars () {

        return $this->call_model('get_query_vars', []);

    }

    public static function get_query_vars () {

        return [];

    }

    public function main_query_wrap ($query) {

        if (!is_archive() || !$this->is_main_query($query)) return;

        $this->main_query($query);

    }

    public function main_query ($wp_query) {

        //$wp_query->query_vars = wp_parse_args(static::get_query_vars(), $wp_query->query_vars);
        //merge_query(static::get_query_vars(), $wp_query);

        $query = new Digitalis_Query($wp_query->query_vars);
        //$query->merge(static::get_query_vars());
        $query->merge($this->query_vars());
        $wp_query->query_vars = $query->get_query();

    }

    // Admin Query

    public function admin_query_vars () {

        return $this->call_model('get_admin_query_vars', []);

    }

    public static function get_admin_query_vars () {

        return [];

    }

    public function admin_query_wrap ($query) {

        if (!$this->is_main_admin_query($query)) return;

        $this->admin_query($query);

    }

    public function admin_query ($wp_query) {

        //$wp_query->query_vars = wp_parse_args(static::get_admin_query_vars(), $wp_query->query_vars);
        //merge_query(static::get_admin_query_vars(), $wp_query);

        $query = new Digitalis_Query($wp_query->query_vars);
        //$query->merge(static::get_admin_query_vars());
        $query->merge($this->admin_query_vars());
        $wp_query->query_vars = $query->get_query();

    }

    //

    public function ajax_query_wrap () {

        global $wp, $wp_query;
        $wp->parse_request();
        $wp_query->query_vars = $wp->query_vars;
        //$wp_query->set('post_type', $this->slug);

        return $this->ajax_query();

    }

    //

    protected function get_filters () {
        
        return $this->filters;
        
    }

    protected function build_filters () {

        if ($filters = $this->get_filters()) foreach ($filters as $key => &$filter) {

            if (!is_array($filter)) $filter = ['type'  => $filter];

            $filter = wp_parse_args($filter, [
                'type'  => 'taxonomy',
                'name'  => $key,
                'args'  => [],
            ]);

            switch ($filter['type']) {

                case 'taxonomy':

                    $taxonomy = get_taxonomy($key);

                    if ($taxonomy) $filter['args'] = wp_parse_args($filter['args'], [
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
                        'hide_falsy'     => true,
                        'compare'        => '=',
                        'label'          => null,
                        'type'           => null,
                        'value_callback' => false,
                        'query_callback' => false,
                    ]);

            }

        }

        $this->filters = $filters;

    }

    public function admin_controller ($query) {

        if (!$this->is_main_admin_query($query)) return;

        $this->build_filters();

        if ($this->filters) foreach ($this->filters as $key => $filter) {

            $value = false;

            switch ($filter['type']) {

                case 'taxonomy':

                    if ($value = $_REQUEST['tax'][$key] ?? false) {

                        if (is_callable($filter['args']['value_callback'])) $value = call_user_func($filter['args']['value_callback'], $value);

                        if (!isset($query->query_vars['tax_query'])) $query->query_vars['tax_query'] = [];

                        $tax_query = [
                            'taxonomy' => $key,
                            'field'    => $filter['args']['value_field'] == 'term_id' ? 'id' : $filter['args']['value_field'],
                            'terms'    => $value,
                        ];

                        if (is_callable($filter['args']['query_callback'])) $tax_query = call_user_func($filter['args']['query_callback'], $tax_query);

                        $query->query_vars['tax_query'][] = $tax_query;

                    }

                    break;

                case 'acf':

                    if ($value = $_REQUEST['meta'][$key] ?? false) {
                        
                        if (is_callable($filter['args']['value_callback'])) $value = call_user_func($filter['args']['value_callback'], $value);

                        if (!isset($query->query_vars['meta_query'])) $query->query_vars['meta_query'] = [];

                        $meta_query = [
                            'key'     => $key,
                            'value'   => $value,
                            'compare' => $filter['args']['compare'],
                        ];

                        if (is_callable($filter['args']['query_callback'])) $meta_query = call_user_func($filter['args']['query_callback'], $meta_query);

                        $query->query_vars['meta_query'][] = $meta_query;

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

                    if (get_taxonomy($key)) wp_dropdown_categories($filter['args']);
                    
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

                    $label = is_null($filter['args']['label']) ? $field['label'] : $filter['args']['label'];

                    if (isset($field['choices']) && ($options = $field['choices'])) {

                        echo "<select name='meta[{$filter['name']}]'>";

                            echo "<option value='0'>Filter by {$field['label']}</option>";

                            foreach ($options as $value => $option_label) {

                                if ($filter['args']['hide_falsy'] && !$value) continue;
                                echo "<option value='{$value}' " . selected($value, $current_value, false) . ">{$option_label}</option>";

                            }

                        echo "</select>";

                    } else {

                        $type = is_null($filter['args']['type']) ? 'text' : $filter['args']['type'];

                        echo "<input type='{$type}' name='meta[{$filter['name']}]' value='{$current_value}' placeholder='Filter by {$label}'>";

                    }

                    break;

            }

        }

        if (method_exists($this, 'after_render_filters')) $this->after_render_filters();

    }

    //

    public function after_insert_wrap ($post_id, $post, $update, $post_before) {

        if ($post->post_type != $this->slug) return;

        $this->after_insert($post_id, $post, $update, $post_before);

    }

    //

    public function register_query_vars_wrap ($vars) {

        return $this->call_model('register_query_vars', $vars, [$vars]);

    }

    public function register_query_vars ($vars) {

        return $vars;

    }

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

}