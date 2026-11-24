<?php

declare(strict_types=1);

namespace Lattice;

use WP_Query;
use WP_Post;

/**
 * Optional subclass hooks; run() wires each only when the subclass defines it.
 * @method mixed ajax_query()
 * @method void  filter_post_data(array &$data, array $postarr, array|object $unsanitized_postarr, bool $update)
 * @method void  after_insert(int $post_id, WP_Post $post, bool $update, ?WP_Post $post_before)
 * @method void  after_delete(int $post_id, WP_Post $post)
 */
abstract class Post_Type extends Singleton {

    protected static $cache_group = self::class;

    const AJAX_Flag = 'digitalis_ajax_query';

    protected $slug         = 'post-type';
    protected $archive      = 'post-types';
    protected $icon         = 'dashicons-format-aside';

    protected $text_domain  = 'default';
    protected $singular     = 'Post Type';
    protected $plural       = null;
    
    protected $position     = 5;
    protected $model_class  = false;
    protected $register     = true;

    //

    protected $post_type;
    protected $removed_supports = [];

    protected function switch_on () : void {

        //if ($flush) flush_rewrite_rules();
        
        if ($this->register) $this->add_action('init', [$this, 'register'], 10, 1);
        $this->add_action('template_redirect', [$this, 'instantiate_model'], 10, 1);

        // Admin Query

        $this->add_action('pre_get_posts', [$this, 'admin_query_wrap'], 10, 1);

        // CRUD

        if (method_exists($this, 'filter_post_data')) $this->add_filter('wp_insert_post_data', [$this, 'filter_post_data_wrap'], 10, 4);
        if (method_exists($this, 'after_insert'))     $this->add_action('wp_after_insert_post', [$this, 'after_insert_wrap'], 10, 4);
        if (method_exists($this, 'after_delete'))     $this->add_action('after_delete_post', [$this, 'after_delete_wrap'], 10, 2);

        // Front

        $this->add_action('query_vars', [$this, 'register_query_vars_wrap'], 10, 1);
        $this->add_action('pre_get_posts', [$this, 'main_query_wrap'], 10, 1);

        if (method_exists($this, 'ajax_query')) {
            
            $action = "query_" . $this->slug;
            $this->add_action("wp_ajax_{$action}", [$this, 'ajax_query_wrap'], 10, 1);
            $this->add_action("wp_ajax_nopriv_{$action}", [$this, 'ajax_query_wrap'], 10, 1);

        }

        parent::switch_on();

    }

    public function run () {}  // Override me :)

    public function register () {

        $args               = $this->get_args($this->get_default_args());
        $args['rewrite']    = $this->get_rewrite($this->get_default_rewrite());
        $args['supports']   = $this->get_supports($this->get_default_supports());
        $args['labels']     = $this->get_labels($this->get_default_labels());

        if ($this->removed_supports && is_array($args['supports'])) { // nothing to remove is the common case: skip the pass

            $supports = [];

            foreach ($args['supports'] as $key => $feature) { // a feature is a value ('title') or a key with arguments ('editor' => [...]), as WP_Post_Type::add_supports() reads it; array_diff() compared values alone, so a keyed feature survived its removal and stringified with a warning

                if (in_array(is_array($feature) ? $key : $feature, $this->removed_supports, true)) continue;
                if (is_array($feature)) $supports[$key] = $feature; else $supports[] = $feature;

            }

            $args['supports'] = $supports;

        }
        if ($args['supports'] === []) $args['supports'] = false; // [] would hand WordPress its defaults back (title, editor, autosave); false is "none"

        $this->filter_args($args);

        $args = Hook::filter(['lattice', 'post_type', static::class, 'args'], $args);

        $this->post_type = register_post_type(
            $this->slug,
            $args
        );
        
    }

    public function instantiate_model () {

        global $wp_query;

        if ($this->model_class && $wp_query && $wp_query->is_singular && $this->is_main_query($wp_query)) {

            $call = "{$this->model_class}::get_instance";

            if (is_callable($call) && ($instance = call_user_func($call))) {
                
                //while (isset($GLOBALS[$global_var])) $global_var = "_{$global_var}";
                if ($var = $instance->get_global_var()) $GLOBALS[$var] = $instance;

            }

        }
        
    }

    //

    protected function remove_support (string $support) {

        $this->removed_supports[] = $support;

    }

    //

    protected function filter_args (array &$args) {
    
        // ...
    
    }

    protected function get_args (array $args) {

        return $args;

    }

    protected function get_rewrite (array|false $rewrite) {     // and this...

        return $rewrite;

    }

    protected function get_supports (array|false $supports) {   // this one to

        return $supports;

    }
   
    protected function get_labels (array $labels) {       // ..also me.

        return $labels;

    }

    protected function plural () {
    
        return $this->plural ? $this->plural : $this->singular . 's';
    
    }

    protected function get_default_args () {

        return [
            'description'           => __($this->plural(), $this->text_domain),
            'public'                => true,
            'publicly_queryable'    => true,
            'menu_position'         => $this->position,
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

        return Hook::filter(['lattice', 'post_type', static::class, 'rewrite'],
        [
            'slug'          => $this->archive,
            'with_front'    => false,
            'pages'         => true,
            'feeds'         => true
        ]);

    }

    protected function get_default_supports () {

        return Hook::filter(['lattice', 'post_type', static::class, 'supports'],
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

        // The noun is the consumer's ($text_domain); the scaffolding around it is the framework's ('lattice').
        $singular = __($this->singular, $this->text_domain);
        $plural   = __($this->plural(), $this->text_domain);

        return Hook::filter(['lattice', 'post_type', static::class, 'labels'],
        [
            'name'               => $plural,
            'singular_name'      => $singular,
            'menu_name'          => $plural,
            'name_admin_bar'     => $singular,
            'add_new'            => sprintf(__('Add New %s',            'lattice'), $singular),
            'add_new_item'       => sprintf(__('Add New %s',            'lattice'), $singular),
            'edit_item'          => sprintf(__('Edit %s',               'lattice'), $singular),
            'new_item'           => sprintf(__('New %s',                'lattice'), $singular),
            'view_item'          => sprintf(__('View %s',               'lattice'), $singular),
            'search_items'       => sprintf(__('Search %s',             'lattice'), $plural),
            'not_found'          => sprintf(__('No %s found',           'lattice'), $plural),
            'not_found_in_trash' => sprintf(__('No %s found in trash',  'lattice'), $plural),
            'all_items'          => $plural,
            'archive_title'      => $plural,
        ]);

    }

    //

    public function call_model (string $method, mixed $default = [], array $args = []) {

        $call = false;

        if ($this->model_class)     $call = $this->model_class . "::" . $method;
        if (!is_callable($call))    $call = static::class . "::" . $method;
        if (!is_callable($call))    $call = [$this, $method];

        return is_callable($call) ? call_user_func_array($call, $args) : $default;
        
    }

    public function query_vars () {

        return $this->call_model('get_query_vars', []);

    }

    public static function get_query_vars () {

        return [];

    }

    public function main_query_wrap (WP_Query $query) {

        if ($this->is_main_query($query) && Query_Vars::is_multiple($query)) {

            $this->main_query($query);

        }

    }

    public function main_query (WP_Query $wp_query) {

        $qv = new Query_Vars($wp_query->query_vars);
        $qv->merge($this->query_vars(), true);
        $wp_query->query_vars = $qv->to_array();

    }

    // Admin Query

    public function admin_query_vars () {

        return $this->call_model('get_admin_query_vars', []);

    }

    public static function get_admin_query_vars () {

        return [];

    }

    public function admin_query_wrap (WP_Query $query) {

        if (!$this->is_main_admin_query($query)) return;

        $this->admin_query($query);

    }

    public function admin_query (WP_Query $wp_query) {

        //$wp_query->query_vars = wp_parse_args(static::get_admin_query_vars(), $wp_query->query_vars);
        //merge_query(static::get_admin_query_vars(), $wp_query);

        $qv = new Query_Vars($wp_query->query_vars);
        $qv->merge($this->admin_query_vars(), true);
        $wp_query->query_vars = $qv->to_array();

    }

    //

    public function ajax_query_wrap () {

        global $wp, $wp_query;
        $wp->parse_request();
        $wp_query->query_vars = $wp->query_vars;
        $wp_query->set('post_type', $this->slug);
        $wp_query->set(self::AJAX_Flag, true);
        $this->main_query($wp_query);

        return $this->ajax_query();

    }

    //

    public function filter_post_data_wrap (array $data, array $postarr, array|object $unsanitized_postarr, bool $update) {
    
        if ($data['post_type'] != $this->slug) return $data;

        $this->filter_post_data($data, $postarr, $unsanitized_postarr, $update);

        return $data;
    
    }

    public function after_insert_wrap (int $post_id, WP_Post $post, bool $update, ?WP_Post $post_before) {

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;

        if ($post->post_type != $this->slug) return;

        // Turning this off as it doesn't honor acf updates
        /* if ($update) {

            $before = (array) $post_before;
            $after  = (array) $post;
    
            unset($before['post_modified']);
            unset($before['post_modified_gmt']);
            unset($after['post_modified']);
            unset($after['post_modified_gmt']);

            if (!array_diff_assoc($before , $after)) return; // For some reason 'wp_after_insert_post' is being called twice.

        } */

        if (isset($_POST['_acf_form'])) {

            add_action('acf/submit_form', function () use (&$post_id, &$post, &$update, &$post_before) {

                $this->after_insert($post_id, $post, $update, $post_before);

            }, 20);

        } else {

            $this->after_insert($post_id, $post, $update, $post_before);

        }

    }

    public function after_delete_wrap (int $post_id, WP_Post $post) {
    
        if ($post->post_type != $this->slug) return;

        $this->after_delete($post_id, $post);
    
    }

    //

    public function register_query_vars_wrap (array $vars) {

        return $this->call_model('register_query_vars', $vars, [$vars]);

    }

    public function register_query_vars (array $vars) {

        return $vars;

    }

    protected function is_main_query (WP_Query $query) {

        return ((!is_admin() || wp_doing_ajax()) && $query->is_main_query() && Query_Vars::compare_post_type($query, $this->slug));

    }

    protected function is_main_admin_query (WP_Query $query) {

        return ($this->is_admin_archive() && $query->is_main_query() && isset($query->query_vars['post_type']) && ($query->query_vars['post_type'] == $this->slug));

    }

    protected function is_admin_archive () {

        global $pagenow, $post_type;

        return (is_admin() && ($post_type == $this->slug) && ($pagenow == 'edit.php'));

    }

}