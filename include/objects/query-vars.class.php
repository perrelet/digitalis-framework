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

    public function get_meta_query () {

        return $this->query['meta_query'];

    }

    public function get_tax_query () {

        return $this->query['tax_query'];

    }

    public function add_meta_query ($meta_query) {

        $this->query['meta_query'][] = $meta_query;

        return $this;

    }

    public function add_tax_query ($tax_query) {

        $this->query['tax_query'][] = $tax_query;

        return $this;

    }

    public function &find_meta_query ($value, $compare = '=', $key = 'key') {

        return $this->find($this->query['meta_query'], $value, $key, $compare);

    }

    public function &find_tax_query ($value, $compare = '=', $key = 'taxonomy') {

        return $this->find($this->query['tax_query'], $value, $compare, $key);

    }

    public function &find (&$var, $value, $compare = '=', $key = 'key') {

        if ($var && is_array($var)) foreach ($var as &$block) {

            if (!is_array($block)) continue;

            if (isset($block[$key])) {

                if ($this->compare($value, $block[$key], $compare)) return $block;

            } else {

                if ($found = $this->find($block, $value, $compare, $key)) return $found;

            }

        }

        return false;

    }


    public function overwrite ($query) {
        
        if ($query) foreach ($query as $key => $value) $this->set_var($key, $value);

        return $this;
        
    }

    public function merge ($query, $merge_falsy = false) {

        if ($query) foreach ($query as $key => $value) {

            if ($merge_falsy || $value) $this->merge_var($key, $value, $merge_falsy);

        }

        return $this;

    }

    public function merge_var ($key, $value, $merge_falsy = false) {
        
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

        if ($merge_falsy || $value) $this->query[$key] = $value; // What about falsey values??

        return $this;

    }

    //

    protected function compare ($v1, $v2, $operator = '=') {

        $operator = strtoupper($operator);

        switch ($operator) {

            case '=':    return $v1 == $v2;
            case '!=':   return $v1 != $v2;
            case '==':   return $v1 === $v2;
            case '!==':  return $v1 !== $v2;
            case '<':    return $v1 < $v2;
            case '<=':   return $v1 <= $v2;
            case '>':    return $v1 > $v2;
            case '>=':   return $v1 >= $v2;
            case 'IN':   return is_array($v2) ? in_array($v1, $v2) : $v1 == $v2;
            case '!IN':  return !$this->compare($v1, $v2, 'IN');
            case 'IN=':  return is_array($v2) ? in_array($v1, $v2, true) : $v1 == $v2;
            case '!IN=': return !$this->compare($v1, $v2, 'IN=');
            default:     return false;

        }

    }

}