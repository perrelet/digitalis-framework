<?php

namespace Digitalis;

class Meta_Box extends Feature {

    use Dependency_Injection;

    protected static $cache_property = 'id';

    protected $id       = 'digitalis-metabox';
    protected $title    = 'Digitalis Metabox';
    protected $screen   = null;
    protected $context  = 'advanced';
    protected $priority = 'default';
    protected $view     = null;
    protected $callback = null;
    protected $args     = [];

    public function run () {

        add_action('add_meta_boxes', [$this, 'add_meta_box']);
    
    }

    public function add_meta_box () {

        if (!$this->condition()) return;
    
        add_meta_box(
            $this->get_id(),
            $this->get_title(),
            [$this, 'render_wrap'],
            $this->get_screen(),
            $this->get_context(),
            $this->get_priority(),
            $this->get_args()
        );
    
    }

    public function condition () {
    
        return true;
    
    }

    //public function render ($object, $args) {}

    public function render_wrap ($object, $args) {

        if ($this->view) {

            Call::static($this->view, 'render', $object, $args);

        } else {

            if (is_null($this->callback))     $this->callback = [$this, 'render'];
            if (is_callable($this->callback)) static::inject($this->callback, [$object, $args]);

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