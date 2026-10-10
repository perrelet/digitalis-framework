<?php

namespace Lattice;

abstract class Shortcode extends Factory {

    protected static $cache_group    = self::class;
    protected static $cache_property = 'slug';

    protected $slug = 'shortcode';
    protected $view = View::class;

    public function __construct () {
        
        add_shortcode($this->slug, [$this, 'render']);
        
    }

    public function render (array|string $atts) {

        if (!is_string($this->view) || !is_subclass_of($this->view, View::class)) return is_string($this->view) ? "Error: \$view must be a subclass of \Lattice\View, '{$this->view}' provided." : "Error: \$view must be the class name of a \Lattice\View subclass, " . (is_object($this->view) ? 'an instance of ' : '') . get_debug_type($this->view) . " provided on " . static::class . ".";

        $defaults = call_user_func("{$this->view}::get_defaults");
        $atts     = shortcode_atts($defaults, $atts, $this->slug);
        $view     = new $this->view($atts);

        return (string) $view;
        
    }

}