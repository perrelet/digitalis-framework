<?php

namespace Digitalis;

abstract class Users_Table extends Screen_Table {

    public function run () {

        $this->slug = 'users';

        parent::run();

        add_action('manage_users_extra_tablenav', [$this, 'render_filters']);
        add_action('pre_get_users',               [$this, 'filter_query']);
    
    }

    protected function get_row_actions_hook ($slug) {
    
        return 'user_row_actions';
    
    }

    public function row_actions_wrap ($actions, $wp_user) {

        static::inject([$this, 'row_actions'], [&$actions, $wp_user]);

        return $actions;

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

        \Digitalis\Field\Input::render([
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

        $qv = new \Digitalis\Query_Vars();
        $qv->merge($query->query_vars, true);

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

                case 'field':

                    if ($filter['query_callback'] && is_callable($filter['query_callback'])) call_user_func($filter['query_callback'], $qv);

                    break;

            }

        }

        $query->query_vars = $qv->to_array();

    }

}