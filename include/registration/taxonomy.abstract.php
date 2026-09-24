<?php

namespace Lattice;

abstract class Taxonomy extends Singleton {

    protected $slug = 'taxonomy';
    protected $post_types = [];

    protected $text_domain  = 'default';
    protected $singular     = 'Taxonomy';
    protected $plural       = 'Taxonomies';

    protected $taxonomy;

    public function __construct () {

        // if ($flush) flush_rewrite_rules();

        add_action('init', [$this, 'register'], 0);

        if (method_exists($this, 'columns'))    add_filter("manage_edit-{$this->slug}_columns",     [$this, 'columns']);
        if (method_exists($this, 'column'))     add_filter("manage_{$this->slug}_custom_column",    [$this, 'column'], 10, 3);

        $this->run();

    }
    
    public function run () {}  // Override me :)

    public function register () {

        $args            = $this->get_args($this->get_default_args());
        $args['rewrite'] = $this->get_rewrite($this->get_default_rewrite());
        $args['labels']  = $this->get_labels($this->get_default_labels());

        $args = Hook::filter(['lattice', 'taxonomy', static::class, 'args'], $args);

        $this->taxonomy = register_taxonomy(
            $this->slug,
            $this->post_types,
            $args
        );

    }

    protected function get_args ($args) {           // You might override this...

        return $args;

    }

    protected function get_rewrite ($rewrite) {     // and this...

        return $rewrite;

    }

    protected function get_labels ($labels) {       // ...and this.

        return $labels;

    }

    protected function get_default_args () {

        return [
            'hierarchical'              => true,
            'public'                    => true,
            'show_ui'                   => true,
            'show_in_menu'              => true,
            'show_admin_column'         => true,
            'show_in_nav_menus'         => true,
            'show_tagcloud'             => false,
            'show_in_rest'              => true,
        ];

    }

    protected function get_default_rewrite () {

        return [
            'slug'          => $this->slug,
            'with_front'    => false
        ];

    }

    protected function get_default_labels () {

        // The noun is the consumer's ($text_domain); the scaffolding around it is the framework's ('lattice').
        $singular = __($this->singular, $this->text_domain);
        $plural   = __($this->plural,   $this->text_domain);

        return [
            'name'                          => $singular,
            'singular_name'                 => $singular,
            'menu_name'                     => $plural,
            'all_items'                     => sprintf(__('All %s',                    'lattice'), $plural),
            'parent_item'                   => sprintf(__('Parent %s',                 'lattice'), $singular),
            'parent_item_colon'             => sprintf(__('Parent %s:',                'lattice'), $singular),
            'new_item_name'                 => sprintf(__('New %s',                    'lattice'), $singular),
            'add_new_item'                  => sprintf(__('Add New %s',                'lattice'), $singular),
            'edit_item'                     => sprintf(__('Edit %s',                   'lattice'), $singular),
            'update_item'                   => sprintf(__('Update %s',                 'lattice'), $singular),
            'view_item'                     => sprintf(__('View %s',                   'lattice'), $singular),
            'separate_items_with_commas'    => sprintf(__('Separate %s with commas',   'lattice'), $plural),
            'add_or_remove_items'           => sprintf(__('Add or remove %s',          'lattice'), $plural),
            'choose_from_most_used'         =>         __('Choose from the most used', 'lattice'),
            'popular_items'                 => sprintf(__('Popular %s',                'lattice'), $plural),
            'search_items'                  => sprintf(__('Search %s',                 'lattice'), $plural),
            'not_found'                     =>         __('Not Found',                 'lattice'),
            'no_terms'                      => sprintf(__('No %s',                     'lattice'), $plural),
            'items_list'                    => sprintf(__('List of %s',                'lattice'), $plural),
            'items_list_navigation'         => sprintf(__('%s list navigation',        'lattice'), $plural),
        ];

    }

    public function get_taxonomy () {
    
        return $this->taxonomy;
    
    }

    //

    /* public function columns ($columns) {

        return $columns;

    }
    
    public function column ($output, $column, $term_id) {

        switch ($column) {

            case '':
                return;

        }


        return $output;

    } */

}