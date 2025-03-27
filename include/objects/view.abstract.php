<?php

namespace Digitalis;

abstract class View implements \ArrayAccess {

    use Dependency_Injection, Inherits_Props;

    protected static $defaults      = [];      // Default params. Inherited by all derivative classes. 
    protected static $required      = [];      // Param keys that are required.
    protected static $merge         = [];      // Param keys to be merged (rather than overridden) in derivative classes.
    protected static $skip_inject   = [];      // Param keys that should skip dependency injection.

    protected static $template      = null;    // The name of the template file to load (omit .php extension).
    protected static $template_path = __DIR__; // Absolute path to the template directory.

    protected static $indexes = [];

    protected static $inherited_props = [
        'defaults',
        'required',
        'merge',
        'skip_inject',
    ];

    public static function render ($params = [], $print = true) {

        $class_name = Call::get_class_name(static::class);
        return (new $class_name($params))->print(!$print);

    }

    public static function get_defaults () {

        return static::get_inherited_prop('defaults', static::get_merge_keys());

    }

    public static function get_required_keys () {

        return static::get_inherited_prop('required');
    
    }

    public static function get_merge_keys () {

        return static::get_inherited_prop('merge');
    
    }

    public static function get_skip_inject_keys () {

        return static::get_inherited_prop('skip_inject');
    
    }

    //

    protected $params = [];

    public function __construct ($params = []) {
    
        $this->set_params(static::get_defaults());
        $this->merge_params($params);

    }

    public function __toString() {

        return $this->print(true);

    }

    public function print ($return = false) {

        if (!isset(self::$indexes[static::class])) self::$indexes[static::class] = 0;

        $this->set_param('index', self::$indexes[static::class]);
        $this->inject_dependencies($this->params, static::get_defaults());
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

    protected function inject_dependencies (&$params, $defaults) {

        foreach (static::get_skip_inject_keys() as $key) if (isset($defaults[$key])) unset($defaults[$key]);
    
        static::array_inject($params, $defaults);
    
    }

    public function params (&$p) {}

    public function validate () {

        if (!$this->required())   return false;
        if (!$this->permission()) return false;
        if (!$this->condition())  return false;

        return true;
    
    }

    public function required () {
    
        $defaults = static::get_defaults();

        foreach (static::get_required_keys() as $key) {

            $value = $this[$key] ?? null;

            if (($class_name = ($defaults[$key] ?? false)) && class_exists($class_name)) {

                if (!($value instanceof $class_name)) return false;

            } else {

                if (is_null($value)) return false;

            }

        }

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

    public function merge_params ($params) {
    
        $this->params = static::deep_parse_args($params, $this->params, static::get_merge_keys());
        return $this;
    
    }

    public function get_param ($key) {
    
        return $this->params[$key] ?? null;
    
    }

    public function set_param ($key = null, $value = null) {
    
        if (is_null($key)) {
            $this->params[] = $value;
        } else {
            $this->params[$key] = $value;
        }

        return $this;
    
    }

    public function unset_param ($key) {
    
        unset($this->params[$key]);
        return $this;
    
    }

    public function has_param ($key) {
    
        return isset($this->params[$key]);
    
    }

    public function merge_param ($key, ...$values) {

        $values             = array_map(fn($value) => is_array($value) ? $value : [$value], $values);
        $values             = call_user_func_array('array_merge', $values);
        $this->params[$key] = array_merge($this->params[$key], $values);
        static::make_list_unique($this->params[$key]);
        return $this;
    
    }

    public function get_index () {
    
        return $this->get_param('index');
    
    }

    public function is_first () {
    
        return $this->get_param('index') === 0;
    
    }

    // Property Overloading

    public function &__get ($key) {

        if (isset($this->params[$key])) { // Terinaries, null coalesce, etc cause `Only variable references should be returned by reference`

            return $this->params[$key];

        } else {

            $null = null;
            return $null;

        }

    }

    public function __set ($key, $value) {

        return $this->set_param($key, $value);

    }

    public function __unset ($key) {

        return $this->unset_param($key);

    }

    public function __isset ($key) {

        return $this->has_param($key);

    }

    // ArrayAccess

    public function &offsetGet ($key) { // Return by reference, see: https://www.php.net/manual/en/arrayaccess.offsetget.php

        return $this->__get($key);

    }

    public function offsetSet ($key, $value) {

        $this->__set($key, $value);

    }

    public function offsetUnset ($key) {

        $this->__unset($key);

    }

    public function offsetExists ($key) {

        return $this->__isset($key);

    }

}