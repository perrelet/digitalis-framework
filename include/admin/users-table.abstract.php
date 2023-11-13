<?php

namespace Digitalis;

abstract class Users_Table extends Admin_Table {

    public function run () {
    
        add_filter('manage_users_columns',        [$this, 'columns']);
        add_filter('manage_users_custom_column',  [$this, 'column'], 10, 3);

        add_action('manage_users_extra_tablenav', [$this, 'render_filters']);
        add_action('pre_get_users',               [$this, 'filter_query']);
    
    }

    public function columns ($columns) {

        return $columns;

    }

    public function column ($output, $column, $user_id) {

        $call = [$this, "column_" . str_replace('-', '_', $column)];

        return is_callable($call) ? call_user_func($call, $output, $user_id) : $output;

    }

    public function default_tax_args ($filter, $taxonomy) {

        $args = parent::default_tax_args($filter, $taxonomy);

        $args['value_field'] = 'term_id';
    
        return $args;
    
    }

    public function get_acf_field ($name) {
    
        return get_field_object($name, 'user_' . get_current_user_id());
    
    }

    public function render_filter_submit () {

        \Digitalis\Field::render([
            'type'    => 'submit',
            'value'   => 'Filter',
            'classes' => ['button'],
            'wrap'    => false,
        ]);

    }

    public function filter_query ($query) {

        global $pagenow;
        if (!is_admin()) return;
        if ('users.php' !== $pagenow) return;

        $filters = $this->get_filters();

        $qv = new \Digitalis\Query_Vars([
            'meta_query' => $query->meta_query ? $query->meta_query : [],
        ]);

        if ($filters) foreach ($filters as $name => $filter) {

            switch ($filter['type']) {

                case 'taxonomy':

                    $value = $filter['args']['selected'];
                    if ($this->null_filter_value($value, $filter)) break;

                    $users = get_objects_in_term($value, $filter['name']);
                    $query->set('include', $users);

                    break;

                case 'acf':

                    $value = $filter['field']['value'];
                    if ($this->null_filter_value($value, $filter)) break;
        
                    $meta_query = [
                        'key'     => $filter['field']['key'],
                        'value'   => $value,
                        'compare' => '='
                    ];
        
                    if ($filter['query_callback'] && is_callable($filter['query_callback'])) $meta_query = call_user_func($filter['query_callback'], $meta_query);
        
                    $qv->add_meta_query($meta_query);

                    break;

            }



        }

        $query->query_vars['meta_query'] = $qv->get_var('meta_query');

    }

}