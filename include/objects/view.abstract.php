<?php

namespace Digitalis;

abstract class View {

    protected static $defaults = [];                                        // Default args. Inherited by all derivative classes. 
    protected static $merge = [];                                           // Selected args will be merged (rather than overridden) by derivative classes.
    protected static $params = [];                                          // Calculated args are cached here.
    protected static $template = null;                                      // The name of the template file to load (.php extension not required). If null provided view will render via the static::view($p) method.
    protected static $template_path = __DIR__ . "/../../templates/";        // Absolute path to the template directory.

    protected static $indexes = [];

    public static function params ($params) { return $params; }

    public static function get_template ($params) { return static::$template; }

    public static function render ($params = [], $print = true) {

        if (!isset(self::$indexes[static::class])) self::$indexes[static::class] = 0;
        $params['index'] = ++self::$indexes[static::class];

        if (method_exists(static::class, 'footer') && !has_action('wp_print_footer_scripts', [static::class, 'footer'])) add_action('wp_print_footer_scripts', [static::class, 'footer']);

        if (!$print) ob_start();

        $defaults   = static::$defaults;
        $class      = static::class;

        while ($class = get_parent_class($class)) {

            if ($class::$merge) foreach ($class::$merge as $arg) {

                if (isset($class::$defaults[$arg]) && is_array($class::$defaults[$arg]) && $class::$defaults[$arg]) {

                    if (!isset($defaults[$arg])) $defaults[$arg] = [];
                    $defaults[$arg] = array_unique(wp_parse_args($defaults[$arg], $class::$defaults[$arg]), SORT_REGULAR);

                }

            }

            $defaults = wp_parse_args($defaults, $class::$defaults);

        }

        static::$params = wp_parse_args($params, $defaults);
        static::$params = static::params(static::$params);

        //

        if ($params['index'] == 1) static::before_first(static::$params);
        static::before(static::$params);

        if (is_null($template = static::get_template(static::$params))) {

            static::view(static::$params);

        } else {

            $path = trailingslashit(static::$template_path) . $template . '.php';

            if (file_exists($path)) {

                extract(static::$params, EXTR_SKIP);
                require $path;

            }

        }

        static::after(static::$params);
        if ($params['index'] == 1) static::after_first(static::$params);

        if (!$print) {
            $html = ob_get_contents();
            ob_end_clean();
            return $html;
        }

    }

    protected static function before ($params) {}
    protected static function after ($params) {}

    protected static function before_first ($params) {}
    protected static function after_first ($params) {}

    protected static function view ($params = []) {}

}