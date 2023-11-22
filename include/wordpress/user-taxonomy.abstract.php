<?php

namespace Digitalis;

class User_Taxonomy extends Taxonomy {

    protected $slug         = 'user-tag';
    protected $post_types   = 'user';

    protected $singular     = 'User Tag';
    protected $plural       = 'User Tags';

    public function __construct () {

        parent::__construct();

        add_action('admin_menu',                            [$this, 'taxonomy_page']);
        add_filter("manage_edit-{$this->slug}_columns",     [$this, 'taxonomy_columns']);
        add_filter("manage_{$this->slug}_custom_column",    [$this, 'taxonomy_column'], 10, 3);
        add_filter('parent_file',                           [$this, 'highlight_menu_item']);

    }

    public function taxonomy_page() {

        add_users_page(
            esc_attr($this->taxonomy->labels->menu_name),
            esc_attr($this->taxonomy->labels->menu_name),
            $this->taxonomy->cap->manage_terms,
            'edit-tags.php?taxonomy=' . $this->slug
        );

    }

    public function taxonomy_columns ($columns) {

        unset($columns['posts']);

        $columns['users'] = 'Users';

        return $columns;

    }

    public function taxonomy_column ($output, $column, $term_id) {

        switch ($column) {

            case 'users':
                
                $term = get_term($term_id, $this->slug);
                $url = admin_url("users.php?{$this->slug}={$term_id}");

                return "<a href='{$url}'>{$term->count}</a>";

        }

        return $output;

    }

    public function highlight_menu_item ($parent_file) {

        global $submenu_file;
        if ($submenu_file == "edit-tags.php?taxonomy={$this->slug}") $parent_file = 'users.php';

        return $parent_file;

    }

}