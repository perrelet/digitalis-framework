<?php

namespace Digitalis;

abstract class View {

    use Dependency_Injection;

    protected static $defaults = [];           // Default args. Inherited by all derivative classes. 
    protected static $merge = [];              // Selected args will be merged (rather than overridden) by derivative classes.
    protected static $skip_inject = [];        // 
    protected static $params = [];             // Calculated args are cached here.
    protected static $template = null;         // The name of the template file to load (.php extension not required). If null provided view will render via the static::view($p) method.
    protected static $template_path = __DIR__; // Absolute path to the template directory.

    protected static $indexes = [];

    public static function params ($params) { return $params; }

    public static function get_template ($params) { return static::$template; }

    public static function get_defaults () {
        
        $defaults   = static::$defaults;
        $class      = static::class;

        while ($class = get_parent_class($class)) {

            if ($class::$merge) foreach ($class::$merge as $key) {

                if (isset($class::$defaults[$key]) && is_array($class::$defaults[$key]) && $class::$defaults[$key]) {

                    if (!isset($defaults[$key])) $defaults[$key] = [];
                    $defaults[$key] = wp_parse_args($defaults[$key], $class::$defaults[$key]);
                    
                    if (array_is_list($defaults[$key])) $defaults[$key] = array_unique($defaults[$key], SORT_REGULAR);

                }

            }

            $defaults = wp_parse_args($defaults, $class::$defaults);

        }

        return $defaults;
        
    }

    protected static function inject_dependencies (&$p, $defaults) {

        if (static::$skip_inject) foreach (static::$skip_inject as $skip) if (isset($defaults[$skip])) unset($defaults[$skip]);
    
        return static::array_inject($p, $defaults);
    
    }

    public static function compute_params ($params = []) {
        
        $defaults = static::get_defaults();

        static::$params = wp_parse_args($params, $defaults);

        //

        if (static::$merge) foreach (static::$merge as $key) {

            if (isset($params[$key]) && is_array($params[$key])) {

                if (!isset($defaults[$key])) $defaults[$key] = [];
                static::$params[$key] = wp_parse_args($params[$key], $defaults[$key]);

            }

        }

        //

        static::inject_dependencies(static::$params, $defaults);

        static::$params = static::params(static::$params);
        
    }

    public static function render ($params = [], $print = true) {

        if (!isset(self::$indexes[static::class])) self::$indexes[static::class] = 1;
        $params['index'] = self::$indexes[static::class];

        if (method_exists(static::class, 'footer') && !has_action('wp_print_footer_scripts', [static::class, 'footer'])) add_action('wp_print_footer_scripts', [static::class, 'footer']);

        static::compute_params($params);

        if (!static::permission(static::$params)) return;
        if (!static::condition(static::$params))  return;

        self::$indexes[static::class]++;

        //

        if (!$print) ob_start();

        if ($params['index'] == 1) static::before_first(static::$params);
        static::before(static::$params);

        if (is_null($template = static::get_template(static::$params))) {

            static::view(static::$params);

        } else {

            $path = trailingslashit(static::$template_path) . $template . '.php';

            if (file_exists($path)) {

                if (static::$params) extract(static::$params, EXTR_OVERWRITE);
                require $path;

            }

        }

        if ($params['index'] == 1) static::after_first(static::$params);
        static::after(static::$params);

        if (!$print) {
            $html = ob_get_contents();
            ob_end_clean();
            return $html;
        }

    }

    protected static function permission ($params) {
        
        return true;
    
    }

    protected static function condition ($params) {
        
        return true;
    
    }

    protected static function before ($params) {}
    protected static function after ($params) {}

    protected static function before_first ($params) {}
    protected static function after_first ($params) {}

    protected static function view ($params = []) {}

}