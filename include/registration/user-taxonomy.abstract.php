<?php

declare(strict_types=1);

namespace Lattice;

use WP_Term;

class User_Taxonomy extends Taxonomy {

    protected $slug         = 'user-tag';
    protected $post_types   = 'user';

    protected $singular     = 'User Tag';
    protected $plural       = 'User Tags';

    protected function switch_on () : void {

        parent::switch_on();

        $this->add_action('admin_menu', [$this, 'taxonomy_page'], 10, 1);
        $this->add_filter("manage_edit-{$this->slug}_columns", [$this, 'taxonomy_columns'], 10, 1);
        $this->add_filter("manage_{$this->slug}_custom_column", [$this, 'taxonomy_column'], 10, 3);
        $this->add_filter('parent_file', [$this, 'highlight_menu_item'], 10, 1);

    }

    public function taxonomy_page() {

        add_users_page(
            esc_attr($this->taxonomy->labels->menu_name),
            esc_attr($this->taxonomy->labels->menu_name),
            $this->taxonomy->cap->manage_terms,
            'edit-tags.php?taxonomy=' . $this->slug
        );

    }

    public function taxonomy_columns (array $columns) {

        unset($columns['posts']);

        $columns['users'] = 'Users';

        return $columns;

    }

    public function taxonomy_column (?string $output, string $column, int $term_id) {

        switch ($column) {

            case 'users':
                
                $wp_term = get_term($term_id, $this->slug);
                if (!($wp_term instanceof WP_Term)) return $output;

                $url = admin_url("users.php?{$this->slug}={$term_id}");

                return "<a href='{$url}'>{$wp_term->count}</a>";

        }

        return $output;

    }

    public function highlight_menu_item (?string $parent_file) {

        global $submenu_file;
        if ($submenu_file == "edit-tags.php?taxonomy={$this->slug}") $parent_file = 'users.php';

        return $parent_file;

    }

}