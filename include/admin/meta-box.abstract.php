<?php

namespace Digitalis;

abstract class Meta_Box extends Feature {

    protected $id       = 'digitalis-metabox';
    protected $title    = 'Digitalis Metabox';
    protected $screen   = null;
    protected $context  = 'advanced';
    protected $priority = 'default';
    protected $view     = null;
    protected $args     = [];

    public function run () {

        add_action('add_meta_boxes', [$this, 'add_meta_box']);
    
    }

    public function add_meta_box () {

        if (!$this->condition()) return;
    
        add_meta_box(
            $this->get_id(),
            $this->get_title(),
            [$this, 'render'],
            $this->get_screen(),
            $this->get_context(),
            $this->get_priority(),
            $this->get_args()
        );
    
    }

    public function condition () {
    
        return true;
    
    }

    public function render ($object, $args) {
    
        if ($this->view) {

            $call = $this->view . "::render";

            if (is_callable($call)) call_user_func($call, $filter['args'], $filter);

        }
    
    }

    //

    public function get_id () {
    
        return $this->id;
    
    }

    public function get_title () {
    
        return __($this->title);
    
    }

    public function get_screen () {
    
        return $this->screen;
    
    }

    public function get_context () {
    
        return $this->context;
    
    }

    public function get_priority () {
    
        return $this->priority;
    
    }

    public function get_args () {
    
        return $this->args;
    
    }

}