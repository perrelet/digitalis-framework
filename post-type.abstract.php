<?php

namespace Digitalis;

abstract class Post_Type extends Base {

    protected $slug         = 'post-type';
    protected $archive      = 'post-types';
    protected $icon         = 'dashicons-format-aside';

    protected $text_domain  = 'default';
    protected $singular     = 'Post Type';
    protected $plural       = 'Post Types';

    public function __construct ($flush = false) {

        if ($flush) flush_rewrite_rules();
        
        add_action('init', [$this, 'register']);

        if (method_exists($this, 'columns')) add_filter("manage_{$this->slug}_posts_columns", [$this, 'columns']);
        if (method_exists($this, 'column')) add_filter("manage_{$this->slug}_posts_custom_column", [$this, 'column'], 10, 2);

        $this->run();

    }

    public function run () {}

    public function register () {

        $args               = $this->get_args($this->get_default_args());
        $args['rewrite']    = $this->get_rewrite($this->get_default_rewrite());
        $args['supports']   = $this->get_supports($this->get_default_supports());
        $args['labels']     = $this->get_labels($this->get_default_labels());

        $args = apply_filters("digitalis-" . $this->get_identifier() . "-args", $args);

        register_post_type(
			$this->slug,
			$args
		);
        
    }


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
            'description'			=> __($this->plural, $this->text_domain),
			'public'				=> true,
			'publicly_queryable'	=> true,
			'menu_position'			=> 5,
			'show_ui'				=> true,
			'show_in_menu'			=> true,
			'menu_icon'				=> $this->icon,
			'can_export'			=> true, 
			'delete_with_user'		=> false,
			'hierarchical'			=> false,
			'has_archive'			=> $this->archive,
			'map_meta_cap'			=> true,
			'show_in_rest' 		=> true
        ];

    }

    protected function get_default_rewrite () {

        return apply_filters("digitalis-" . $this->get_identifier() . "-rewrite",
        [
            'slug'			=> $this->archive,
            'with_front'	=> false,
            'pages'			=> true,
            'feeds'			=> true
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
            'name'               => __( $this->plural,					        $this->text_domain ),
            'singular_name'      => __( $this->singular,						$this->text_domain ),
            'menu_name'          => __( $this->plural,	                        $this->text_domain ),
            'name_admin_bar'     => __( $this->plural,					        $this->text_domain ),
            'add_new'            => __( "Add New {$this->singular}",			$this->text_domain ),
            'add_new_item'       => __( "Add New {$this->singular}",			$this->text_domain ),
            'edit_item'          => __( "Edit {$this->singular}",				$this->text_domain ),
            'new_item'           => __( "New {$this->singular}",				$this->text_domain ),
            'view_item'          => __( "View {$this->singular}",				$this->text_domain ),
            'search_items'       => __( "Search {$this->plural}",			    $this->text_domain ),
            'not_found'          => __( "No {$this->plural} found",			    $this->text_domain ),
            'not_found_in_trash' => __( "No {$this->plural} found in trash",	$this->text_domain ),
            'all_items'          => __( $this->plural,			    		    $this->text_domain ),
            'archive_title'      => __( $this->plural,			    		    $this->text_domain ),
        ]);

    }

}