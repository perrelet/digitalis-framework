<?php

namespace Digitalis;

use WP_Query;

class Digitalis_Query extends WP_Query {

    // Defer calling $this->query($query) in constructor

    public function __construct ($query = []) {

        $this->query = $query;

    }

    // Called explicitly

    public function query ($query = []) {

        if ($query) $this->merge($query);

        return parent::query($this->query);
        
    }

    // Extended:

    public function get_query () {

        return $this->query;

    }

    public function set_query ($query) {

        $this->query = $query;

        return $this;

    }

    public function get_var ($key, $default = '') {

        return isset($this->query[$key]) ? $this->query[$key] : $default;

    }

    public function set_var ($key, $value) {

        $this->query[$key] = $value;

        return $this;

    }

    public function overwrite ($query) {
        
        if ($query) foreach ($query as $key => $value) {

            $this->set_var($key, $value);

        }

        return $this;
        
    }

    public function merge_var ($key, $value) {
        
        if (isset($this->query[$key]) && ($existing_value = $this->query[$key])) {

            switch ($key) {

                case 'post_type':
                case 'post_status':

                    if (($value == 'any') || ($existing_value == 'any')) {

                        $value = 'any';
                        break;

                    }

                    if (!is_array($value)) $value = [$value];
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

    public function merge ($query) {

        if ($query) foreach ($query as $key => $value) {

            if (!$value) continue;

            $this->merge_var($key, $value);

        }

        return $this;

    }

}