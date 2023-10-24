<?php

namespace Digitalis;

class Query_Vars {

    protected $query;

    public function __construct ($query_vars = []) {

        $this->set_vars($query_vars);

    }

    public function get_vars () {
    
        return $this->query;
    
    }

    public function to_array () {

        return $this->get_vars();

    }

    public function set_vars ($query_vars) {
    
        $this->query = wp_parse_args($query_vars, [
            'meta_query' => [],
            'tax_query'  => [],
        ]);

        return $this;
    
    }

    public function get_var ($key, $default = null) {

        return isset($this->query[$key]) ? $this->query[$key] : $default;

    }

    public function set_var ($key, $value) {

        $this->query[$key] = $value;

        return $this;

    }

    public function add_meta_query ($meta_query) {

        $this->query['meta_query'][] = $meta_query;

        return $this;

    }

    public function add_tax_query ($tax_query) {

        $this->query['tax_query'][] = $tax_query;

        return $this;

    }

    public function overwrite ($query) {
        
        if ($query) foreach ($query as $key => $value) $this->set_var($key, $value);

        return $this;
        
    }

    public function merge ($query) {

        if ($query) foreach ($query as $key => $value) {

            if (!$value) continue;

            $this->merge_var($key, $value);

        }

        return $this;

    }

    public function merge_var ($key, $value) {
        
        if ($existing_value = $this->query[$key] ?? null) {

            switch ($key) {

                case 'post_type':
                case 'post_status':

                    if (($value == 'any') || ($existing_value == 'any')) {

                        $value = 'any';
                        break;

                    }

                    if (!is_array($value))          $value          = [$value];
                    if (!is_array($existing_value)) $existing_value = [$existing_value];

                    // no break;

                case 'tax_query':
                case 'meta_query':

                    // no break;

                default:

                    if (is_array($value) && is_array($existing_value)) $value = array_unique(array_merge($existing_value, $value), SORT_REGULAR);
                
            }

        }

        if ($value) $this->query[$key] = $value;

        return $this;

    }

}