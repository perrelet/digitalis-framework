<?php

namespace Digitalis;

class User_Taxonomy extends Taxonomy {

    protected $slug         = 'user-tag';
    protected $post_types   = 'user';

    protected $singular     = 'User Tag';
    protected $plural       = 'User Tags';

    protected $filters = [
        'select'    => 'Filter by User Tag',
        'text'      => 'Enter a User Tag',
    ];

    public function __construct ($flush = false) {

        parent::__construct($flush);

        add_action('admin_menu',                            [$this, 'taxonomy_page']);
        add_filter("manage_edit-{$this->slug}_columns",     [$this, 'taxonomy_columns']);
        add_filter("manage_{$this->slug}_custom_column",    [$this, 'taxonomy_column'], 10, 3);
        add_filter('parent_file',                           [$this, 'highlight_menu_item']);
        add_action('pre_get_users',                         [$this, 'admin_filter_query']);

        if ($this->filters) add_action('manage_users_extra_tablenav', [$this, 'render_filters']);

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

    // Admin Filters

    protected function get_select_terms () {

        return get_terms([
            'taxonomy'      => $this->slug,
            'hide_empty'    => 'true',
            'orderby'       => 'count',
            'order'         => 'DESC',
            'number'        => 50,
        ]);

    }

    public function render_filters () {

        echo "<form method='GET'>";

            echo "<div class='alignleft actions'>";

                if (isset($this->filters['select'])) {

                    $terms = $this->get_select_terms();
                    $current_term_id = isset($_REQUEST[$this->slug] ) ? sanitize_text_field(wp_unslash($_REQUEST[$this->slug])) : false;

                    echo "<select name='{$this->slug}'><option value=''>{$this->filters['select']}</option>";

                    if ($terms) foreach ($terms as $term) {
    
                        echo "<option value='{$term->term_id}' " . selected($term->term_id, $current_term_id, false) . ">" . esc_html($term->name) . "</option>";
            
                    }
    
                    echo "</select>";

                }
        
                if (isset($this->filters['text'])) {

                    $current_term_name = isset($_REQUEST["{$this->slug}-text"] ) ? sanitize_text_field(wp_unslash($_REQUEST["{$this->slug}-text"])) : false;
                    echo "<input type='text' name='{$this->slug}-text' value='{$current_term_name}' placeholder='{$this->filters['text']}'>";

                }

                echo "<input type='submit' class='button' value='Filter'>";

            echo "</div>";

        echo "</form>";

    }

    public function admin_filter_query ($query) {

        global $pagenow;

        if (!is_admin() || ($pagenow !== 'users.php'))                                  return;
        if (!isset($_REQUEST[$this->slug]) && !isset($_REQUEST["{$this->slug}-text"]))  return;

        $term_ids = [];

        if (isset($_REQUEST[$this->slug])) {

            $select = trim(sanitize_text_field($_REQUEST[$this->slug]));
            $term_ids[] = $select;

        }

        if (isset($_REQUEST["{$this->slug}-text"])) {

            $text = trim(sanitize_text_field($_REQUEST["{$this->slug}-text"]));

            $term = false;
            if (!$term && ($term = get_term_by('name',      $text, $this->slug))) $term_ids[] = $term->term_id;
            if (!$term && ($term = get_term_by('slug',      $text, $this->slug))) $term_ids[] = $term->term_id;
            if (!$term && ($term = get_term_by('term_id',   $text, $this->slug))) $term_ids[] = $term->term_id;

        }

        if ($term_ids) {

            $users = get_objects_in_term($term_ids, $this->slug);
            $query->set('include', $users);

        }

    }

}