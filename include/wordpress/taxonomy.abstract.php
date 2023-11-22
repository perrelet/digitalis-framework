<?php

namespace Digitalis;

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

        $args = apply_filters("Digitalis/Taxonomy/" . str_replace('\\', '/', ltrim(static::class, '\\')) . "/Args", $args);

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

        return [
            'name'                          => __( $this->singular,                         $this->text_domain ),
            'singular_name'                 => __( $this->singular,                         $this->text_domain ),
            'menu_name'                     => __( $this->plural,                           $this->text_domain ),
            'all_items'                     => __( "All {$this->plural}",                   $this->text_domain ),
            'parent_item'                   => __( "Parent {$this->singular}",              $this->text_domain ),
            'parent_item_colon'             => __( "Parent {$this->singular}:",             $this->text_domain ),
            'new_item_name'                 => __( "New {$this->singular}",                 $this->text_domain ),
            'add_new_item'                  => __( "Add New {$this->singular}",             $this->text_domain ),
            'edit_item'                     => __( "Edit {$this->singular}",                $this->text_domain ),
            'update_item'                   => __( "Update {$this->singular}",              $this->text_domain ),
            'view_item'                     => __( "View {$this->singular}", 	            $this->text_domain ),
            'separate_items_with_commas'    => __( "Separate {$this->plural} with commas",  $this->text_domain ),
            'add_or_remove_items'           => __( "Add or remove {$this->plural}",         $this->text_domain ),
            'choose_from_most_used'         => __( "Choose from the most used",             $this->text_domain ),
            'popular_items'                 => __( "Popular {$this->plural}",               $this->text_domain ),
            'search_items'                  => __( "Search {$this->plural}",                $this->text_domain ),
            'not_found'                     => __( "Not Found",                             $this->text_domain ),
            'no_terms'                      => __( "No {$this->plural}",                    $this->text_domain ),
            'items_list'                    => __( "List of {$this->plural}",               $this->text_domain ),
            'items_list_navigation'         => __( "{$this->plural} list navigation",       $this->text_domain ),
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