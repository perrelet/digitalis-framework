<?php

namespace Digitalis;

abstract class View implements \ArrayAccess {

    use Dependency_Injection, Inherits_Props;

    protected static $defaults      = [];      // Default args. Inherited by all derivative classes. 
    protected static $merge         = [];      // Selected args will be merged (rather than overridden) by derivative classes.
    protected static $skip_inject   = [];      // 
    protected static $template      = null;    // The name of the template file to load (.php extension not required). If null provided view will render via the static::view($p) method.
    protected static $template_path = __DIR__; // Absolute path to the template directory.

    protected static $indexes       = [];
    protected static $merge_storage = [];

    public static function render ($params = [], $print = true) {

        $class_name = Call::get_class_name(static::class);

        return (new $class_name($params))->print(!$print);

    }

    public static function get_defaults () {

        return static::inherit_merge_array('defaults', static::get_merge_keys());

    }

    protected static function compute_merge_keys () {
    
        $merge_keys = [];
        $class      = static::class;

        while ($class = get_parent_class($class)) $merge_keys = array_merge($class::$merge, $merge_keys);
    
        return array_unique($merge_keys);
    
    }

    public static function get_merge_keys () {

        if (!isset(self::$merge_storage[static::class])) self::$merge_storage[static::class] = static::compute_merge_keys();

        return self::$merge_storage[static::class];
    
    }

    public static function compute_params (&$params = []) {

        $defaults = static::get_defaults();
        $params   = static::deep_parse_args($params, $defaults, static::get_merge_keys());
        $params   = static::inject_dependencies($params, $defaults);

    }

    protected static function inject_dependencies ($params, $defaults) {

        if (static::$skip_inject) foreach (static::$skip_inject as $skip) if (isset($defaults[$skip])) unset($defaults[$skip]);
    
        return static::array_inject($params, $defaults);
    
    }

    //

    protected $params;

    public function __construct ($params = []) {
    
        $this->set_params($params);
    
    }

    public function __toString() {

        return $this->print(true);

    }

    public function print ($return = false) {

        if (!isset(self::$indexes[static::class])) self::$indexes[static::class] = 0;
        $this->set_param('index', self::$indexes[static::class]);

        static::compute_params($this->params);
        $this->params($this->params);

        if (!$this->validate()) return '';

        self::$indexes[static::class]++;

        if ($return) ob_start();

        if ($this->is_first()) $this->before_first();
        $this->before();

        if ($template = $this->get_template()) {

            $path = realpath(trailingslashit($this->get_template_path()) . $template . '.php');

            if (file_exists($path)) {

                extract($this->params, EXTR_OVERWRITE);
                require $path;

            }

        } else {

            $this->view();

        }

        if ($this->is_first()) $this->after_first();
        $this->after();

        if ($return) {

            $html = ob_get_contents();
            ob_end_clean();
            return $html;

        }
    
    }

    public function params (&$p) {}

    public function validate () {
    
        if (!$this->permission()) return false;
        if (!$this->condition())  return false;

        return true;
    
    }

    public function permission () {
    
        return true;
    
    }

    public function condition () {
    
        return true;
    
    }

    public function get_template_path () {
        
        return static::$template_path;
    
    }

    public function get_template () {
        
        return static::$template;
    
    }

    public function before_first () {}
    public function before       () {}
    public function view         () {}
    public function after_first  () {}
    public function after        () {}

    // Params

    public function get_params () {
    
        return $this->params;
    
    }

    public function set_params ($params) {
    
        $this->params = $params;
        return $this;
    
    }

    public function get_param ($key) {
    
        return $this->params[$key] ?? null;
    
    }

    public function set_param ($key, $value) {
    
        $this->params[$key] = $value;
        return $this;
    
    }

    public function unset_param ($key) {
    
        unset($this->params[$key]);
        return $this;
    
    }

    public function has_param ($key) {
    
        return isset($this->params[$key]);
    
    }

    public function merge_params ($params) {
    
        $this->params = static::deep_parse_args($params, $this->params, static::get_merge_keys());
        return $this;
    
    }

    public function get_index () {
    
        return $this->get_param('index');
    
    }

    public function is_first () {
    
        return $this->get_param('index') === 0;
    
    }

    // ArrayAccess

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