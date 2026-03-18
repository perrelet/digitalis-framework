<?php

namespace Digitalis;

abstract class Shortcode extends Factory {

    protected static $cache_group    = self::class;
    protected static $cache_property = 'slug';

    protected $slug = 'shortcode';
    protected $view = View::class;

    public function __construct () {
        
        add_shortcode($this->slug, [$this, 'render']);
        
    }

    public function render ($atts) {

        if (!is_subclass_of($this->view, View::class)) return "Error: \$view must be a subclass of \Digitalis\View, '{$this->view}' provided.";

        $defaults = call_user_func("{$this->view}::get_defaults");
        $atts     = shortcode_atts($defaults, $atts, $this->slug);
        $view     = new $this->view($atts);

        return (string) $view;
        
    }

}