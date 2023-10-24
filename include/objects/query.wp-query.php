<?php

namespace Digitalis;

use WP_Query;

class Digitalis_Query extends WP_Query {

    protected $query_vars_obj;

    // Defer calling $this->query($query) in constructor

    public function __construct ($query = []) {

        $this->query_vars_obj = new Query_Vars($query);

    }

    // Called explicitly

    public function query ($query = []) {

        if ($query) $this->merge($query);

        return parent::query($this->get_query_vars());
        
    }

    // Extended:

    public function get_query_vars_obj () {
    
        return $this->query_vars_obj;
    
    }

    public function get_query_vars () {

        return $this->query_vars_obj->get_vars();

    }

    public function set_query ($query) {

        $this->query_vars_obj->set_vars($query);

        return $this;

    }

    public function get_var ($key, $default = null) {

        return $this->query_vars_obj->get_var($key, $default);

    }

    public function set_var ($key, $value) {

        $this->query_vars_obj->set_var($key, $value);

        return $this;

    }

    public function add_meta_query ($meta_query) {

        $this->query_vars_obj->add_meta_query($meta_query);

        return $this;

    }

    public function add_tax_query ($tax_query) {

        $this->query_vars_obj->add_tax_query($tax_query);

        return $this;

    }

    public function overwrite ($query) {
        
        $this->query_vars_obj->overwrite($query);

        return $this;
        
    }

    public function merge ($query) {

        $this->query_vars_obj->merge($query);

        return $this;

    }

    public function merge_var ($key, $value) {

        $this->query_vars_obj->merge_var($key, $value);

        return $this;
        
    }

}