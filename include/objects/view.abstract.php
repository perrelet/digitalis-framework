<?php

namespace Digitalis;

abstract class View implements \ArrayAccess {

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

    public static function get_merge_keys () {

        $merge_keys = [];
        $class      = static::class;

        while ($class = get_parent_class($class)) $merge_keys = array_merge($class::$merge, $merge_keys);
    
        return array_unique($merge_keys);
    
    }

    public static function get_defaults () {
        
        $defaults   = static::$defaults;
        $class      = static::class;

        while ($class = get_parent_class($class)) {

            foreach (static::get_merge_keys() as $key) {

                if (isset($class::$defaults[$key]) && is_array($class::$defaults[$key]) && $class::$defaults[$key]) {

                    if (!isset($defaults[$key])) $defaults[$key] = [];
                    $defaults[$key] = wp_parse_args((array) $defaults[$key], (array) $class::$defaults[$key]);
                    
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

        foreach (static::get_merge_keys() as $key) {

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

    //

    protected $instance_params;
    protected $configuration;

    public function __construct ($params = []) {
    
        $this->set_params($params);
    
    }

    public function __toString() {

        return $this->print(true);

    }

    public function get_params () {
    
        return $this->instance_params;
    
    }

    public function set_params ($params) {
    
        $this->instance_params = $params;
        return $this;
    
    }

    public function get_param ($key) {
    
        return $this->instance_params[$key] ?? null;
    
    }

    public function set_param ($key, $value) {
    
        $this->instance_params[$key] = $value;
        return $this;
    
    }

    public function unset_param ($key) {
    
        unset($this->instance_params[$key]);
        return $this;
    
    }

    public function has_param ($key) {
    
        return isset($this->instance_params[$key]);
    
    }
    
    }

    public function print ($return = false) {
    
        $result = static::render($this->instance_params, !$return);
        $this->configuration = static::$params;
        return $result;
    
    }

    //

    public function offsetGet ($key) {

        return $this->get_param($key);

    }

    public function offsetSet ($key, $value) {

        $this->set_param($key, $value);

    }

    public function offsetUnset ($key) {

        $this->unset_param($key);

    }

    public function offsetExists ($key) {

        return $this->has_param($key);

    }

}