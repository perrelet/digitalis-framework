<?php

namespace Digitalis;

use \WP_Styles;
abstract class Theme {

    protected $actions = [
        'wp_enqueue_scripts'    => 'style',
        'after_setup_theme'     => 'theme_supports',
    ];

    protected $features;

    public static function get_auto_instantiation () { return true; }
    
    public function __construct () {

        foreach ($this->actions as $action => $method) if (is_callable([$this, $method])) add_action($action, [$this, $method]);

        if (method_exists($this, 'init')) $this->init();

    }

    //

    protected function register_features () { // Features registered by child classes will be automatically appended to the features array

        return [
            'js_modules' => function () {
                add_filter('script_loader_tag', [$this, 'add_modules_attribute'], 10, 3);
            },
        ];

    }

    protected function get_features () {

        if (is_null($this->features)) {

            $this->features = $this->register_features();

            $class = get_called_class();
            while ($class = get_parent_class($class)) $this->features += call_user_func($class . "::" . 'register_features');

        }

        return $this->features;

    }

    protected function activate_feature ($slug) {

        if (isset($this->get_features()[$slug])) {

            $func = $this->get_features()[$slug];
            $func();

        }

    }

    public function theme_supports () {

        // ..

    }

    // Consider removing / depreciating:

    protected $url;
    protected $path;

    public function set_location ($url, $path) {

        $this->url = $url;
        $this->path = $path;

    }

    public function get_url () {

        return $this->url;

    }

    public function get_path () {

        return $this->path;

    }

    //

    public function enqueue_style_last ($handle, $src, $deps = [], $version = false) {

        if (Page_Builder_Manager::get_instance()->is_backend_ui()) return;

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

    public function add_modules_attribute ($tag, $handle, $src) {

        if (strpos($handle, '-module')) $tag = str_replace("<script", "<script type='module'", $tag);

        return $tag;

    }

}

