<?php

namespace Digitalis;

use Digitalis\Field\Input;

abstract class Admin_Table extends Feature {

    protected $filters = []; // name => type (taxonomy | acf) || name => [type => $type, args => [ ... ], ...] || 'months_dropdown'
    protected $_filters;

    public function filters () {

        return []; // name => type (taxonomy | acf) || name => [type => $type, args => [ ... ], ...] || 'months_dropdown'

    }

    public function get_filters () {

        if (is_null($this->_filters)) {

            $this->_filters = [];

            $this->filters = wp_parse_args($this->filters(), $this->filters);
        
            if ($this->filters) foreach ($this->filters as $name => &$filter) {

                if (!is_array($filter)) $filter = [
                    'type' => $filter
                ];

                $filter = wp_parse_args($filter, [
                    'type'              => 'acf',
                    'name'              => $name,
                    'args'              => [],
                    'value_callback'    => false,
                    'query_callback'    => false,
                    'null_label'        => null,
                    'null_label_prefix' => '',
                    'null_value'        => null,
                    'select_nice'       => false,
                ]);

                switch ($filter['type']) {

                    case 'taxonomy':

                        $taxonomy = get_taxonomy($name);

                        if ($taxonomy) $filter['args'] = wp_parse_args($filter['args'], $this->default_tax_args($filter, $taxonomy));

                        $this->prepare_tax_field($filter);

                        $this->_filters[] = $filter;

                        break;

                    case 'acf':

                        $filter = wp_parse_args($filter, [
                            'hide_falsy'     => true, // --
                            'compare'        => '=',
                            'sub_fields'     => true,
                            'field'          => [],
                        ]);

                        if (!$field = $this->get_acf_field($name)) break;

                        $field = wp_parse_args($filter['field'], $field);

                        if (isset($field['sub_fields']) && $field['sub_fields']) {

                            if ($filter['sub_fields']) foreach ($field['sub_fields'] as $sub_field) {

                                if (
                                    ($filter['sub_fields'] === true) ||
                                    (is_array($filter['sub_fields']) && (in_array($sub_field['name'], $filter['sub_fields']) || isset($filter['sub_fields'][$sub_field['name']])))
                                ) {

                                    $sub_field = wp_parse_args(
                                        $filter['sub_fields'][$sub_field['name']]['field'] ?? [],
                                        $sub_field
                                    );

                                    $sub_filter = wp_parse_args(
                                        $filter['sub_fields'][$sub_field['name']] ?? [],
                                        $filter
                                    );

                                    $sub_filter['parent'] = $field;
                                    $sub_filter['field']  = $sub_field;
                                    
                                    $this->prepare_acf_field($sub_filter);
                                    $this->_filters[] = $sub_filter;

                                }

                            }

                        } else {

                            $filter['parent'] = false;
                            $filter['field']  = $field;
                            
                            $this->prepare_acf_field($filter);
                            $this->_filters[] = $filter;

                        }

                        break;

                    case 'meta':

                        $filter = wp_parse_args($filter, [
                            'compare' => '=',
                        ]);

                        if (!$filter['query_callback']) $filter['query_callback'] = [$this, 'query_callback_meta_compare'];

                        $filter['args'] = wp_parse_args($filter['args'] ?? [], [
                            'field' => Input::class,
                            'key'   => $name,
                            'wrap'  => false,
                        ]);

                        $this->prepare_meta_field($filter);
                        $this->_filters[] = $filter;

                        break;

                    case 'field':

                        $filter['args'] = wp_parse_args($filter['args'] ?? [], [
                            'field' => Input::class,
                            'key'   => $name,
                            'wrap'  => false,
                        ]);

                        $this->_filters[] = $filter;

                        break;


                }

            }

        }

        return $this->_filters;
        
    }

    public function default_tax_args ($filter, $taxonomy) {
    
        return [
            'taxonomy'          => $filter['name'],
            'name'              => $filter['name'],
            'value_field'       => 'slug',
            'hide_empty'        => true,
            'hide_if_empty'     => true,
            'show_option_none'  => $filter['null_label'] ? $filter['null_label'] : $taxonomy->labels->singular_name,
            'option_none_value' => $filter['null_value'],
        ];
    
    }

    public function prepare_tax_field (&$filter) {

        $filter['args']['selected'] = $this->get_filter_value($filter['name'], $filter);

        if ($filter['value_callback'] && is_callable($filter['value_callback'])) $filter['args']['selected'] = call_user_func($filter['value_callback'], $filter['args']['selected']);

        if ($filter['null_label_prefix']) $filter['args']['show_option_none'] = $filter['null_label_prefix'] . $filter['args']['show_option_none'];

    }

    public function get_acf_field ($name) {

        $post_ids = (new \WP_Query([
            'post_type'      => $this->post_type,
            'status'         => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]))->get_posts();

        $id = $post_ids ? $post_ids[0] : false;
    
        return get_field_object($name, $id);
    
    }

    public function prepare_acf_field (&$filter) {

        $field  = &$filter['field'];
        $parent = &$filter['parent'] ?? false;

        $field['prefix'] = null;
        $field['key']    = $parent ? $parent['name'] . '_' . $field['name'] : $field['name'];
        $field['value']  = $this->get_filter_value($field['key'], $filter);

        if ($filter['value_callback'] && is_callable($filter['value_callback'])) $field['value'] = call_user_func($filter['value_callback'], $field['value']);

        if ($field['label'] && ($filter['null_label'] !== false)) {

            $null_label = $filter['null_label_prefix'] . (is_null($filter['null_label']) ? $field['label'] : $filter['null_label']);

            if (isset($field['choices']) && $field['choices']) {
        
                $field['choices'] = array_merge([
                    $filter['null_value'] => $null_label
                ], $field['choices']);

            } else {

                $field['placeholder'] = $null_label;

            }

        }

    }

    public function prepare_meta_field (&$filter) {

        $field          = &$filter['args'];
        $field['value'] = $this->get_filter_value($field['key'], $filter);

    }

    public function get_filter_value ($key, $filter) {

        $value = wp_unslash($_GET[$key] ?? $filter['null_value']);
        return sanitize_text_field(urldecode($value));

    }

    public function null_filter_value ($value, $filter) {

        return $value == $filter['null_value'];
    
    }

    protected $filters_rendered = false;

    public function render_filters () {

        if ($this->filters_rendered) return;
        $this->filters_rendered = true;
    
        $this->before_render_filters();

        if ($filters = $this->get_filters()) foreach ($filters as $filter) {

            switch ($filter['type']) {

                case 'taxonomy':

                    $this->render_tax_filter($filter);
                    break;

                case 'acf':

                    $this->render_acf_filter($filter);
                    break;

                case 'meta':

                    $this->render_meta_filter($filter);
                    break;

                case 'field':
                    
                    $this->render_field_filter($filter);
                    break;
            }

        }

        $this->after_render_filters();
        if ($filters) $this->render_filter_submit();

        echo "<style>.tablenav .acf-input-wrap { display: inline-block; }</style>";
    
    }

    public function render_tax_filter ($filter) {

        if (get_taxonomy($filter['name'])) {

            if ($filter['select_nice']) {

                $terms = get_terms([
                    'taxonomy'      => $filter['name'],
                    'hide_empty'    => 'true',
                    'orderby'       => 'count',
                    'order'         => 'DESC',
                ]);
    
                $options = [];
                if ($filter['args']['show_option_none']) $options[$filter['args']['option_none_value']] = $filter['args']['show_option_none'];

                if ($terms) foreach ($terms as $term) $options[$term->term_id] = $term->name;
    
                \Digitalis\Field\Select_Nice::render([
                    'key'     => $filter['name'],
                    'wrap'    => false,
                    'options' => $options,
                ]); 

            } else {

                wp_dropdown_categories($filter['args']);

            }

        }
    
    }

    public function render_acf_filter ($filter) {

        acf_render_field($filter['field']);
    
    }

    public function render_meta_filter ($filter) {

        $field_class = $filter['args']['field'];
    
        $call = $field_class . "::render";

        if (is_callable($call)) call_user_func($call, $filter['args']);
    
    }

    public function render_field_filter ($filter) {

        $field_class = $filter['args']['field'];
    
        $call = $field_class . "::render";

        if (is_callable($call)) call_user_func($call, $filter['args']);
    
    }

    public function before_render_filters () {}

    public function after_render_filters () {}
    
    public function render_filter_submit () {}

    public function filter_query ($query) {

        $filters = $this->get_filters();

        $qv = new Query_Vars();
        $qv->merge($query->query_vars);

        if ($filters) foreach ($filters as $name => $filter) {

            switch ($filter['type']) {

                /* case 'taxonomy':

                    $value = $filter['args']['selected'];
                    if ($this->null_filter_value($value, $filter)) break;

                    $users = get_objects_in_term($value, $filter['name']);
                    $query->set('include', $users);

                    break; */

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

                case 'meta':

                    if ($filter['query_callback'] && is_callable($filter['query_callback'])) call_user_func($filter['query_callback'], $qv, $filter);

                    break;

                case 'field':

                    if ($filter['query_callback'] && is_callable($filter['query_callback'])) call_user_func($filter['query_callback'], $qv);

                    break;

            }

        }

        $query->query_vars = $qv->to_array();

    }

    //

    protected function query_callback_meta_compare ($qv, $filter) {
    
        if ($filter['args']['value'] != $filter['null_value']) {

            $qv->add_meta_query([
                'key'     => $filter['args']['key'] ?? $filter['name'],
                'value'   => $filter['args']['value'],
                'compare' => $filter['compare'],
            ]);

        }

        return $qv;
    
    }

    protected function query_callback_meta_equal ($qv, $filter) {
    
        if ($filter['args']['value'] != $filter['null_value']) {

            $qv->add_meta_query([
                'key'     => $filter['args']['key'] ?? $filter['name'],
                'value'   => $filter['args']['value'],
                'compare' => '=',
            ]);

        }

        return $qv;
    
    }

    /* public function filter_query ($query) {
    
        $filters = $this->get_filters();

        if ($filters) foreach ($filters as $name => $filter) {

            $value = false;

            switch ($filter['type']) {

                case 'taxonomy':

                    if ($value = $_REQUEST['tax'][$name] ?? false) {

                        //if (is_callable($filter['args']['value_callback'])) $value = call_user_func($filter['args']['value_callback'], $value);

                        if (!isset($query->query_vars['tax_query'])) $query->query_vars['tax_query'] = [];

                        $tax_query = [
                            'taxonomy' => $name,
                            'field'    => $filter['args']['value_field'] == 'term_id' ? 'id' : $filter['args']['value_field'],
                            'terms'    => $value,
                        ];

                        if (is_callable($filter['query_callback'])) $tax_query = call_user_func($filter['query_callback'], $tax_query);

                        $query->query_vars['tax_query'][] = $tax_query;

                    }

                    break;

                case 'acf':

                    if ($value = $_REQUEST['meta'][$name] ?? false) {
                        
                        //if (is_callable($filter['args']['value_callback'])) $value = call_user_func($filter['args']['value_callback'], $value);

                        if (!isset($query->query_vars['meta_query'])) $query->query_vars['meta_query'] = [];

                        $meta_query = [
                            'key'     => $name,
                            'value'   => $value,
                            'compare' => $filter['compare'],
                        ];

                        if (is_callable($filter['query_callback'])) $meta_query = call_user_func($filter['query_callback'], $meta_query);

                        $query->query_vars['meta_query'][] = $meta_query;

                    }

                    break;

            }

        }
    
    } */

}