<?php

namespace Digitalis;

abstract class Post_Status extends Singleton {

    protected $slug        = 'post-status';
    protected $post_types  = 'post';

    protected $singular    = 'Post Status';
    protected $plural      = 'Post Statuses';
    protected $text_domain = 'default';

    protected $args        = [];

    protected $position    = false;
    protected $before      = false;

    protected $register    = true;
    protected $add_to_ui   = true;

    protected $post_status;
    protected $_args;

    protected function filter_args (&$args) {
    
        // ...
    
    }

    public function &get_args () {

        if (is_null($this->_args)) {

            $this->_args = wp_parse_args($this->args, [
                'label'                     => $this->singular,
                'label_count'               => _n_noop($this->singular, $this->plural),
                'exclude_from_search'       => null,
                '_builtin'                  => false,
                'public'                    => null,
                'internal'                  => null,
                'protected'                 => null,
                'private'                   => null,
                'publicly_queryable'        => null,
                'show_in_admin_status_list' => null,
                'show_in_admin_all_list'    => null,
                'date_floating'             => null,         
            ]);

            $this->args = &$this->_args;
            $this->filter_args($this->args);

        }

        return $this->_args;

    }

    public function __construct () {

        $this->get_args();

        if (!is_array($this->post_types)) $this->post_types = [$this->post_types];

        if ($this->register)  add_action('init', [$this, 'register']);
        if ($this->add_to_ui) $this->add_to_ui();

        //add_filter('wc_order_statuses', 'misha_add_status_to_list');
    
    }

    public function register () {

        $this->post_status = register_post_status($this->slug, $this->args);

    }

    public function add_to_ui () {

        add_action('admin_footer-post.php', function () {

            global $post;
    
            if (in_array($post->post_type, $this->post_types)){

                $selected = '';

                $js = "jQuery(document).ready(function($){";
                
                if ($post->post_status == $this->slug) {
    
                    $selected = " selected='selected'";
                    $js      .= "$(`#post-status-display`).html(` {$this->args['label']}`);";
    
                }
                
                $option = "<option value='{$this->slug}'{$selected}>{$this->args['label']}</option>";

                $js .= "let post_status_pos = $(`select#post_status option[value='{$this->position}']`);";
                $js .= "if (post_status_pos.length) {";
                    if ($this->after) {
                        $js .= "$(`{$option}`).insertAfter(post_status_pos);";
                    } else {
                        $js .= "$(`{$option}`).insertBefore(post_status_pos);";
                    }
                $js .= "} else {";
                    $js .= "$(`select#post_status`).append(`{$option}`);";
                $js .= "}";
                $js .= "});";

                echo "<script>{$js}</script>";

            }

        });

        add_filter('display_post_states', function ($states) {

            global $post;

            $arg = get_query_var('post_status');

            if (($arg != $this->slug) && ($post->post_status == $this->slug)) return [$this->args['label']];

            return $states;

        });
    
    }

}