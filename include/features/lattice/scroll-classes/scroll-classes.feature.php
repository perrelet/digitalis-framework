<?php

// Add scroll-aware body classes: 'scrolled', 'scroll-up', 'scroll-down'.

namespace Digitalis;

class Scroll_Classes extends Feature {

    protected $offset     = 1;
    protected $hysteresis = 0;

    public function __construct () {

        $this->add_action('wp_enqueue_scripts', 'enqueue');

    }

    public function enqueue () {

        $handle = 'digitalis-scroll-classes';

        wp_enqueue_script($handle, plugin_dir_url(__FILE__) . 'scroll-classes.js', [], null, true);

        wp_localize_script($handle, 'digitalis_scroll_style', [
            'offset'     => (int) $this->offset,
            'hysteresis' => (int) $this->hysteresis,
        ]);

    }

}
