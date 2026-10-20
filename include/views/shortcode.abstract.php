<?php

namespace Lattice;

abstract class Shortcode extends Factory {

    protected static $cache_group    = self::class;
    protected static $cache_property = 'slug';

    protected $slug = 'shortcode';
    protected $view = false;

    public function __construct () {
        
        add_shortcode($this->slug, [$this, 'render']);
        
    }

    public function render (array|string $atts) {

        if ($error = View::class_error($this->view)) {

            Strict::fail(static::class, $error, 'Set $view to the View class name, or override render().');
            Strict::log_once(static::class, $error);

            return '';

        }

        $class    = Call::get_class_name($this->view); // through the lattice.class.{name} filter, as View::render() does
        $defaults = $class::get_defaults();
        $atts     = shortcode_atts($defaults, $atts, $this->slug);
        $view     = new $class($atts);

        return (string) $view;
        
    }

}