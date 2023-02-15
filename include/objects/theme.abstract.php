<?php

namespace Digitalis;

use \WP_Styles;
abstract class Theme {

    protected $actions = [
        'wp_enqueue_scripts' => 'style',
    ];

    protected $url;
    protected $path;
    
    public function __construct () {

        foreach ($this->actions as $action => $method) if (is_callable([$this, $method])) add_action($action, [$this, $method]);

        if (method_exists($this, 'init')) $this->init();

    }

    //

    public function set_location ($url, $path) {

        $this->url = $url;
        $this->path = $path;

    }

    //

    public function enqueue_style_last ($handle, $src, $deps = [], $version = false) {

        if (defined("SHOW_CT_BUILDER") && !defined("OXYGEN_IFRAME")) return;

        global $digitalis_styles;

        if (is_null($digitalis_styles)) {

            $digitalis_styles = new WP_Styles();

            add_action('wp_head', function () {

                global $digitalis_styles;
                $digitalis_styles->do_items();
    
            }, PHP_INT_MAX);

        }

        $digitalis_styles->add($handle, $src, $deps, $version);
        $digitalis_styles->enqueue($handle);
    
    }

    //

    public function get_url () {

        return $this->url;

    }

    public function get_path () {

        return $this->path;

    }

}

