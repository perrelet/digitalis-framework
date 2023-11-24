<?php

namespace Digitalis;

abstract class User_Role extends Singleton {

    protected $slug     = 'user-role';
    protected $singular = 'Role';
    protected $caps     = [];

    protected $_caps;

    protected function filter_caps (&$caps) {
    
        // ...
    
    }

    protected function &get_caps () {

        if (is_null($this->_caps)) {

            $this->_caps = wp_parse_args($this->caps, []);
            $this->caps  = &$this->_caps;
            $this->filter_caps($this->caps);

        }

        return $this->caps;

    }

    public function __construct () {

        Task_Handler::get_instance()->add_task("add_role_{$this->slug}", [$this, 'register']);

    }

    public function register () {

        add_role($this->slug, $this->singular, $this->get_caps());

    }

}