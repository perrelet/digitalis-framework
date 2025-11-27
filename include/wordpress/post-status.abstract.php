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
                'label'                     => $this->get_singular(),
                'label_count'               => _n_noop($this->get_singular(), $this->get_plural()),
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

        $this->post_types = (array) $this->post_types;

        if ($this->get_register())  add_action('init', [$this, 'register']);
        if ($this->get_add_to_ui()) $this->add_to_ui();

        //add_filter('wc_order_statuses', 'misha_add_status_to_list');
    
    }

    public function get_slug () {

        return $this->slug;

    }

    public function get_post_types () {

        return $this->post_types;

    }

    public function get_singular () {

        return $this->singular;

    }

    public function get_plural () {

        return $this->plural;

    }

    public function get_text_domain () {

        return $this->text_domain;

    }

    public function get_position () {

        return $this->position;

    }

    public function get_register () {

        return $this->register;

    }

    public function get_add_to_ui () {

        return $this->add_to_ui;

    }

    public function register () {

        $this->post_status = register_post_status($this->get_slug(), $this->args);

    }

    public function add_to_ui () {

        add_action('admin_footer-post.php', function () {

            global $post;
    
            if (in_array($post->post_type, $this->get_post_types())){

                $selected = '';

                $js = "jQuery(document).ready(function($){";
                
                if ($post->post_status == $this->get_slug()) {
    
                    $selected = " selected='selected'";
                    $js      .= "$(`#post-status-display`).html(` {$this->args['label']}`);";
    
                }
                
                $option = "<option value='{$this->get_slug()}'{$selected}>{$this->args['label']}</option>";

                $js .= "let post_status_pos = $(`select#post_status option[value='{$this->get_position()}']`);";
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

            if (($arg != $this->get_slug()) && ($post->post_status == $this->get_slug())) return [$this->args['label']];

            return $states;

        });
    
    }

}